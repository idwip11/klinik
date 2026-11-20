<?php

declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

final class ExaminationPdfExporter
{
    /** @param array<string,mixed> $examination @param list<array<string,mixed>> $panelForm */
    public static function validateFormat(array $examination, string $format, array $panelForm = []): void
    {
        if (!in_array($examination['status'] ?? '', ['draft', 'completed', 'finalized'], true)) {
            throw new ValidationException(['form' => 'Hasil pemeriksaan tidak dapat diunduh.']);
        }
        $type = (string) ($examination['examination_type'] ?? '');
        if ($format === 'dl-dll' && $type === 'mcu_general') {
            return;
        }
        if ($format === 'panel-print' && $type === 'mcu_general' && $panelForm !== []) {
            return;
        }
        if (in_array($format, ExaminationType::documentTypes($type), true) && ExaminationType::isValid($type)) {
            return;
        }
        throw new ValidationException(['form' => 'Jenis PDF tidak sesuai dengan pemeriksaan.']);
    }

    /**
     * Reuse the print views so results, reference snapshots, and QR verification
     * never have a separate PDF-specific clinical data mapping.
     * @param array<string,mixed> $examination
     * @param list<array<string,mixed>> $flags
     * @param list<array<string,mixed>> $panelForm
     */
    public static function html(array $examination, array $flags, string $format, array $panelForm = []): string
    {
        self::validateFormat($examination, $format, $panelForm);
        $template = match ($format) {
            'dl-dll' => 'dl_dll',
            'panel-print' => 'panel_print',
            default => 'preview',
        };
        $documentType = $format;
        $autoPrint = false;
        ob_start();
        try {
            require ROOT_PATH . '/app/views/examinations/' . $template . '.php';
            $markup = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $markup . '</body></html>', LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $xpath = new DOMXPath($document);
        $sheet = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " print-sheet ")]')->item(0);
        if (!$sheet instanceof DOMElement) {
            throw new RuntimeException('Format cetak PDF tidak ditemukan.');
        }

        // Dompdf supports CSS tables, not browser grid/flex. Convert identity
        // lists only; leave all clinical result tables exactly as printed.
        foreach (iterator_to_array($xpath->query('.//dl', $sheet)) as $summary) {
            self::convertPatientSummary($document, $summary);
        }
        foreach ($xpath->query('.//table[contains(concat(" ", normalize-space(@class), " "), " print-table ")]', $sheet) as $table) {
            $class = $table->getAttribute('class');
            $widths = str_contains($class, 'dl-dll-table') || str_contains($class, 'panel-print-table')
                ? [30, 15, 7, 13, 35]
                : (str_contains($class, 'drug-print-table') ? [42, 24, 34] : []);
            if ($widths === []) {
                continue;
            }
            // Explicit cell widths also work when a repeated heading spans all
            // columns; relying on nth-child alone makes Dompdf use equal widths.
            foreach ($xpath->query('.//tr', $table) as $row) {
                $cells = $xpath->query('./th|./td', $row);
                if ($cells->length !== count($widths)) {
                    continue;
                }
                foreach ($cells as $index => $cell) {
                    $cell->setAttribute('style', 'width: ' . $widths[$index] . '%');
                }
            }
        }
        foreach (iterator_to_array($xpath->query('.//svg', $sheet)) as $svg) {
            $image = $document->createElement('img');
            $image->setAttribute('src', 'data:image/svg+xml;base64,' . base64_encode((string) $document->saveXML($svg)));
            $image->setAttribute('class', 'pdf-contact-icon');
            $svg->parentNode->replaceChild($image, $svg);
        }
        foreach ($xpath->query('.//img', $sheet) as $image) {
            $source = $image->getAttribute('src');
            // Embed only known clinic artwork. No network calls or arbitrary
            // local paths are accepted from patient data or request input.
            if (in_array($source, ['/assets/header-basmalah.png', '/assets/footer-basmalah.png'], true)) {
                $bytes = file_get_contents(self::assetDirectory() . '/' . basename($source));
                if ($bytes === false) {
                    throw new RuntimeException('Gambar kop klinik tidak dapat dibaca.');
                }
                $image->setAttribute('src', 'data:image/png;base64,' . base64_encode($bytes));
            }
        }

        $css = file_get_contents(ROOT_PATH . '/app/views/examinations/pdf.css');
        if ($css === false) {
            throw new RuntimeException('Stylesheet PDF tidak ditemukan.');
        }
        return '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Hasil pemeriksaan '
            . View::e($examination['lab_number'] ?? '') . '</title><style>'
            . $css . '</style></head><body>' . $document->saveHTML($sheet) . '</body></html>';
    }

    /** @param array<string,mixed> $examination @param list<array<string,mixed>> $flags @param list<array<string,mixed>> $panelForm */
    public static function build(array $examination, array $flags, string $format, array $panelForm = []): string
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('Pustaka PDF belum terpasang. Jalankan composer install.');
        }
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot([self::assetDirectory(), ROOT_PATH . '/vendor/dompdf/dompdf/lib/fonts']);
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsFontSubsettingEnabled(true);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml(self::html($examination, $flags, $format, $panelForm), 'UTF-8');
        $pdf->addInfo('Title', 'Hasil pemeriksaan ' . (string) ($examination['lab_number'] ?? ''));
        $pdf->addInfo('Author', Env::get('CLINIC_NAME', 'Klinik Laboratorium'));
        $pdf->render();
        return $pdf->output();
    }

    /** @param array<string,mixed> $examination */
    public static function filename(array $examination, string $format): string
    {
        $lab = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($examination['lab_number'] ?? $examination['id'] ?? 'hasil'));
        $safeFormat = preg_replace('/[^a-z_-]/', '', $format);
        return 'hasil-' . $safeFormat . '-' . substr((string) $lab, 0, 80) . '.pdf';
    }

    /** @param array<string,mixed> $examination @param list<array<string,mixed>> $flags @param list<array<string,mixed>> $panelForm */
    public static function download(array $examination, array $flags, string $format, array $panelForm = []): never
    {
        $bytes = self::build($examination, $flags, $format, $panelForm);
        AuditLogger::record('examination.pdf_downloaded', Auth::user()['id'] ?? null, 'examination', (int) $examination['id'], null, ['document_type' => $format]);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . self::filename($examination, $format) . '"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    private static function assetDirectory(): string
    {
        // Shared hosting uses public_html while local development uses public.
        return is_dir(ROOT_PATH . '/public_html/assets')
            ? ROOT_PATH . '/public_html/assets'
            : ROOT_PATH . '/public/assets';
    }

    private static function convertPatientSummary(DOMDocument $document, DOMNode $summary): void
    {
        $table = $document->createElement('table');
        $table->setAttribute('class', 'pdf-patient-summary');
        $row = null;
        $pairs = 0;
        foreach (iterator_to_array($summary->childNodes) as $item) {
            if (!$item instanceof DOMElement) {
                continue;
            }
            $label = $item->getElementsByTagName('dt')->item(0);
            $value = $item->getElementsByTagName('dd')->item(0);
            if ($label === null || $value === null) {
                continue;
            }
            $wide = str_contains($item->getAttribute('class'), 'wide');
            $rightColumn = str_contains($item->getAttribute('class'), 'right-column');
            if ($row === null || $pairs === 2 || $wide || ($rightColumn && $pairs === 0)) {
                $row = $document->createElement('tr');
                $table->appendChild($row);
                $pairs = 0;
            }
            if ($rightColumn && $pairs === 0) {
                $row->appendChild($document->createElement('td'));
                $row->appendChild($document->createElement('td'));
                $pairs = 1;
            }
            $rightPair = $rightColumn || $pairs === 1;
            $cellLabel = $document->createElement('td');
            $cellLabel->setAttribute('class', $rightPair ? 'pdf-patient-label pdf-patient-right-label' : 'pdf-patient-label');
            $cellLabel->appendChild($document->createTextNode($label->textContent));
            $row->appendChild($cellLabel);
            $cellValue = $document->createElement('td');
            $cellValue->setAttribute('class', $rightPair ? 'pdf-patient-value pdf-patient-right-value' : 'pdf-patient-value pdf-patient-left-value');
            $cellValue->appendChild($document->createTextNode($value->textContent));
            if ($wide) {
                $cellValue->setAttribute('colspan', '3');
            }
            $row->appendChild($cellValue);
            $pairs++;
            if ($wide) {
                $row = null;
            }
        }
        if ($row !== null && $pairs === 1) {
            $empty = $document->createElement('td');
            $empty->setAttribute('colspan', '2');
            $row->appendChild($empty);
        }
        $summary->parentNode->replaceChild($table, $summary);
    }
}
