<?php

declare(strict_types=1);

final class ReportExcelExporter
{
    /**
     * @param list<string> $headers
     * @param list<list<string|int|float>> $rows
     * @param list<int> $widths
     */
    public static function downloadSimple(string $filename, string $sheetName, array $headers, array $rows, array $widths, string $title): never
    {
        self::downloadWorkbook($filename, [[
            'name' => $sheetName,
            'headers' => $headers,
            'rows' => $rows,
            'widths' => $widths,
        ]], $title);
    }

    /**
     * Membuat workbook sederhana untuk rekap agregat. Setiap elemen $sheets adalah
     * satu tab mandiri agar pengguna tidak perlu menyaring tabel datar yang panjang.
     *
     * @param list<array{name:string,headers:list<string>,rows:list<list<string|int|float>>,widths:list<int>}> $sheets
     */
    public static function downloadWorkbook(string $filename, array $sheets, string $title): never
    {
        if ($sheets === []) {
            throw new InvalidArgumentException('Workbook Excel harus memiliki minimal satu sheet.');
        }

        $path = tempnam(sys_get_temp_dir(), 'clinic-analytics-');
        if ($path === false) {
            throw new RuntimeException('File sementara untuk ekspor Excel tidak dapat dibuat.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Workbook Excel tidak dapat dibuat.');
            }
            $zip->addFromString('[Content_Types].xml', self::aggregateContentTypes(count($sheets)));
            $zip->addFromString('_rels/.rels', self::rootRelationships());
            $zip->addFromString('xl/workbook.xml', self::aggregateWorkbook($sheets));
            $zip->addFromString('xl/_rels/workbook.xml.rels', self::aggregateWorkbookRelationships(count($sheets)));
            $zip->addFromString('xl/styles.xml', self::aggregateStyles());
            foreach ($sheets as $index => $sheet) {
                $zip->addFromString(
                    'xl/worksheets/sheet' . ($index + 1) . '.xml',
                    self::aggregateWorksheet($sheet['headers'], $sheet['rows'], $sheet['widths'])
                );
            }
            $zip->addFromString('docProps/core.xml', self::coreProperties($title));
            $zip->addFromString('docProps/app.xml', self::appProperties());
            $zip->close();

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) filesize($path));
            readfile($path);
        } finally {
            @unlink($path);
        }

        exit;
    }

    /** @param list<array<string, mixed>> $rows */
    public static function download(array $rows): never
    {
        $logbookRows = [];
        $mcuRows = [];
        foreach ($rows as $row) {
            if (($row['examination_type'] ?? '') === 'drug_screen') {
                $logbookRows[] = self::logbookRow($row, count($logbookRows) + 1);
            } else {
                $mcuRows[] = self::mcuRow($row, count($mcuRows) + 1);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'clinic-report-');
        if ($path === false) {
            throw new RuntimeException('File sementara untuk ekspor Excel tidak dapat dibuat.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Workbook Excel tidak dapat dibuat.');
            }
            $zip->addFromString('[Content_Types].xml', self::contentTypes());
            $zip->addFromString('_rels/.rels', self::rootRelationships());
            $zip->addFromString('xl/workbook.xml', self::workbook());
            $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelationships());
            $zip->addFromString('xl/styles.xml', self::styles());
            $zip->addFromString('xl/worksheets/sheet1.xml', self::worksheet($logbookRows, self::logbookHeaders(), self::logbookWidths()));
            $zip->addFromString('xl/worksheets/sheet2.xml', self::worksheet($mcuRows, self::mcuHeaders(), self::mcuWidths()));
            $zip->addFromString('docProps/core.xml', self::coreProperties());
            $zip->addFromString('docProps/app.xml', self::appProperties());
            $zip->close();

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="rekap-pemeriksaan-' . date('Y-m-d') . '.xlsx"');
            header('Content-Length: ' . (string) filesize($path));
            readfile($path);
        } finally {
            @unlink($path);
        }

        exit;
    }

    /** @return list<string> */
    private static function logbookHeaders(): array
    {
        return ['No Urut', 'Nomor Lab', 'No. RM', 'Nama', 'Asuransi', 'L/P', 'Tanggal Lahir', 'Usia', 'Alamat', 'Tanggal Periksa', 'Jam Periksa', 'Amphetamine', 'Marijuana', 'Morphine', 'Benzodiazepine', 'Metamphetamine', 'Cocaine', 'NIK', 'Pekerjaan', 'Keperluan'];
    }

    /** @return list<string> */
    private static function mcuHeaders(): array
    {
        return ['NO', 'NO RM', 'No. Lab', 'Nama', 'Asuransi', 'NIK', 'ttl', 'L/P', 'td', 'tb', 'bb', 'RR', 'BUTA WARNA', 'NADI', 'GOLDAR', 'Rhesus', 'HGB', 'WBC', 'PLT', 'RBC', 'HCT', 'GDS', 'CHOL', 'UA', 'SGOT', 'SGPT', 'Warna Urine', 'Kejernihan', 'Berat Jenis', 'pH', 'Protein Urin', 'Glukosa/Reduksi', 'Urobilinogen', 'Bilirubin', 'Darah Samar', 'Keton', 'Nitrit', 'Lekosit', 'Eritrosit', 'Lekosit2', 'Squamos', 'Silinder', 'Kristal', 'Bakteri', 'No. HP', 'WIDAL O', 'WIDAL AO', 'WIDAL BO', 'WIDAL H', 'ALAMAT LENGKAP', 'status lab', 'kondisi', 'NO. SURAT', 'suhu', 'Tanggal Periksa'];
    }

    /** @return list<int> */
    private static function logbookWidths(): array
    {
        return [10, 18, 14, 28, 22, 14, 24, 8, 42, 18, 12, 15, 15, 15, 18, 18, 15, 22, 20, 28];
    }

    /** @return list<int> */
    private static function mcuWidths(): array
    {
        return [8, 14, 18, 28, 22, 22, 25, 14, 12, 10, 10, 8, 16, 10, 10, 10, 10, 12, 14, 12, 12, 12, 12, 12, 12, 12, 18, 16, 14, 10, 18, 20, 16, 14, 16, 12, 12, 14, 14, 14, 14, 14, 14, 14, 18, 12, 12, 12, 12, 45, 20, 28, 16, 10, 18];
    }

    /** @return list<array{value: mixed, numeric: bool}> */
    private static function logbookRow(array $row, int $number): array
    {
        return self::typedRow([
            $number, $row['lab_number'], $row['medical_record_number'], $row['full_name'], $row['insurance_type'], self::sex($row['sex'] ?? null),
            self::birthText($row), self::age($row['birth_date'] ?? null, $row['examined_on'] ?? null), $row['address'],
            self::dateText($row['examined_on'] ?? null), self::timeText($row['examined_time'] ?? null),
            $row['drug_amphetamine'], $row['drug_marijuana'], $row['drug_morphine'], $row['drug_benzodiazepine'], $row['drug_methamphetamine'], $row['drug_cocaine'],
            $row['national_id'], $row['occupation'], $row['purpose'],
        ]);
    }

    /** @return list<array{value: mixed, numeric: bool}> */
    private static function mcuRow(array $row, int $number): array
    {
        return self::typedRow([
            $number, $row['medical_record_number'], $row['lab_number'], $row['full_name'], $row['insurance_type'], $row['national_id'], self::birthText($row), self::sex($row['sex'] ?? null),
            self::bloodPressure($row), self::number($row['height_cm'] ?? null), self::number($row['weight_kg'] ?? null), self::number($row['respiratory_rate'] ?? null), $row['color_vision'], self::number($row['pulse_rate'] ?? null),
            $row['blood_group'], $row['rhesus'], self::number($row['hgb'] ?? null), self::number($row['wbc'] ?? null), self::number($row['plt'] ?? null), self::number($row['rbc'] ?? null), self::number($row['hct'] ?? null),
            self::number($row['gds'] ?? null), self::number($row['cholesterol'] ?? null), self::number($row['uric_acid'] ?? null), self::number($row['sgot'] ?? null), self::number($row['sgpt'] ?? null),
            $row['urine_color'], $row['urine_clarity'], self::number($row['urine_specific_gravity'] ?? null), self::number($row['urine_ph'] ?? null), $row['urine_protein'], $row['urine_glucose_reduction'],
            $row['urine_urobilinogen'], $row['urine_bilirubin'], $row['urine_occult_blood'], $row['urine_ketone'], $row['urine_nitrite'], $row['urine_leukocyte_esterase'], $row['urine_erythrocytes'], $row['urine_leukocytes'],
            $row['urine_squamous_cells'], $row['urine_casts'], $row['urine_crystals'], $row['urine_bacteria'], $row['phone'], $row['widal_o'], $row['widal_ao'], $row['widal_bo'], $row['widal_h'],
            $row['address'], self::status((string) ($row['status'] ?? '')), $row['condition_note'], $row['letter_number'], self::number($row['temperature_c'] ?? null), self::dateText($row['examined_on'] ?? null),
        ]);
    }

    /** @param list<mixed> $values @return list<array{value: mixed, numeric: bool}> */
    private static function typedRow(array $values): array
    {
        return array_map(static fn(mixed $value): array => ['value' => $value, 'numeric' => is_int($value) || is_float($value)], $values);
    }

    private static function sex(?string $sex): string
    {
        return $sex === 'L' ? 'Laki-laki' : ($sex === 'P' ? 'Perempuan' : '');
    }

    private static function age(mixed $birthDate, mixed $examinedOn): ?int
    {
        if (!$birthDate || !$examinedOn) {
            return null;
        }
        try {
            $birth = new DateTimeImmutable((string) $birthDate);
            $examined = new DateTimeImmutable((string) $examinedOn);
        } catch (Throwable) {
            return null;
        }
        return $examined < $birth ? null : (int) $birth->diff($examined)->y;
    }

    private static function birthText(array $row): string
    {
        $place = trim((string) ($row['birth_place'] ?? ''));
        $date = self::dateText((string) ($row['birth_date'] ?? ''));
        return trim($place . ($place !== '' && $date !== '' ? ', ' : '') . $date);
    }

    private static function dateText(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date === false ? $value : $date->format('d/m/Y');
    }

    private static function timeText(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? '' : substr($value, 0, 5);
    }

    private static function bloodPressure(array $row): string
    {
        $systolic = self::numberText($row['blood_pressure_systolic'] ?? null);
        $diastolic = self::numberText($row['blood_pressure_diastolic'] ?? null);
        return $systolic !== '' && $diastolic !== '' ? $systolic . '/' . $diastolic : '';
    }

    private static function number(mixed $value): int|float|null
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || !is_numeric(str_replace(',', '.', $value))) {
            return null;
        }
        $number = (float) str_replace(',', '.', $value);
        return fmod($number, 1.0) === 0.0 ? (int) $number : $number;
    }

    private static function numberText(mixed $value): string
    {
        return $value === null || trim((string) $value) === '' ? '' : View::formatNumber($value);
    }

    private static function status(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'finalized' => 'Final',
            default => 'Tersimpan',
        };
    }

    /** @param list<array{value: mixed, numeric: bool}> $rows @param list<string> $headers @param list<int> $widths */
    private static function worksheet(array $rows, array $headers, array $widths): string
    {
        $lastRow = count($rows) + 1;
        $lastColumn = self::columnName(count($headers));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:' . $lastColumn . $lastRow . '"/>';
        $xml .= '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="20"/><cols>';
        foreach ($widths as $index => $width) {
            $column = $index + 1;
            $xml .= '<col min="' . $column . '" max="' . $column . '" width="' . $width . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        $xml .= '<row r="1" ht="28" customHeight="1">';
        foreach ($headers as $index => $header) {
            $xml .= self::cell(self::columnName($index + 1) . '1', $header, 1, false);
        }
        $xml .= '</row>';
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $xml .= '<row r="' . $excelRow . '">';
            foreach ($row as $columnIndex => $cell) {
                $xml .= self::cell(self::columnName($columnIndex + 1) . $excelRow, $cell['value'], $cell['numeric'] ? 3 : 4, $cell['numeric']);
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData><autoFilter ref="A1:' . $lastColumn . $lastRow . '"/><pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
        return $xml;
    }

    private static function cell(string $reference, mixed $value, int $style, bool $numeric): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($numeric) {
            return '<c r="' . $reference . '" s="' . $style . '"><v>' . self::xml((string) $value) . '</v></c>';
        }
        return '<c r="' . $reference . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::xml((string) $value) . '</t></is></c>';
    }

    private static function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder) . $name;
            $number = intdiv($number - 1, 26);
        }
        return $name;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
    }

    private static function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><fileVersion appName="xl"/><workbookPr/><bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="12000"/></bookViews><sheets><sheet name="Logbook" sheetId="1" r:id="rId1"/><sheet name="LOGBOOK MCU" sheetId="2" r:id="rId2"/></sheets><calcPr calcId="191029"/></workbook>';
    }

    private static function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.##"/></numFmts><fonts count="2"><font><sz val="10"/><name val="Arial"/><family val="2"/><color rgb="FF172033"/></font><font><b/><sz val="10"/><name val="Arial"/><family val="2"/><color rgb="FFFFFFFF"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF356B59"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9E2E3"/></left><right style="thin"><color rgb="FFD9E2E3"/></right><top style="thin"><color rgb="FFD9E2E3"/></top><bottom style="thin"><color rgb="FFD9E2E3"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="49" fontId="0" fillId="0" borderId="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function aggregateContentTypes(int $sheetCount): string
    {
        $worksheets = '';
        for ($sheet = 1; $sheet <= $sheetCount; $sheet++) {
            $worksheets .= '<Override PartName="/xl/worksheets/sheet' . $sheet . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . $worksheets . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
    }

    /** @param list<array{name:string,headers:list<string>,rows:list<list<string|int|float>>,widths:list<int>}> $sheets */
    private static function aggregateWorkbook(array $sheets): string
    {
        $sheetXml = '';
        foreach ($sheets as $index => $sheet) {
            $sheetXml .= '<sheet name="' . self::xml(self::sheetName($sheet['name'], $index + 1)) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><fileVersion appName="xl"/><workbookPr/><bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="12000"/></bookViews><sheets>' . $sheetXml . '</sheets><calcPr calcId="191029"/></workbook>';
    }

    private static function aggregateWorkbookRelationships(int $sheetCount): string
    {
        $relationships = '';
        for ($sheet = 1; $sheet <= $sheetCount; $sheet++) {
            $relationships .= '<Relationship Id="rId' . $sheet . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheet . '.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    /** @param list<string> $headers @param list<list<string|int|float>> $rows @param list<int> $widths */
    private static function aggregateWorksheet(array $headers, array $rows, array $widths): string
    {
        $lastRow = count($rows) + 1;
        $lastColumn = self::columnName(count($headers));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>';
        $xml .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="20"/><cols>';
        foreach ($widths as $index => $width) {
            $column = $index + 1;
            $xml .= '<col min="' . $column . '" max="' . $column . '" width="' . max(8, $width) . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData><row r="1" ht="30" customHeight="1">';
        foreach ($headers as $index => $header) {
            $xml .= self::aggregateCell(self::columnName($index + 1) . '1', $header, 1, false);
        }
        $xml .= '</row>';
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $xml .= '<row r="' . $excelRow . '">';
            foreach ($row as $columnIndex => $value) {
                $xml .= self::aggregateCell(
                    self::columnName($columnIndex + 1) . $excelRow,
                    $value,
                    2,
                    is_int($value) || is_float($value)
                );
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData><autoFilter ref="A1:' . $lastColumn . $lastRow . '"/><pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';

        return $xml;
    }

    private static function aggregateCell(string $reference, string|int|float $value, int $style, bool $numeric): string
    {
        if ($value === '') {
            return '';
        }
        if ($numeric) {
            return '<c r="' . $reference . '" s="' . $style . '"><v>' . self::xml((string) $value) . '</v></c>';
        }

        return '<c r="' . $reference . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::xml((string) $value) . '</t></is></c>';
    }

    private static function aggregateStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Arial"/><family val="2"/><color rgb="FF172033"/></font><font><b/><sz val="10"/><name val="Arial"/><family val="2"/><color rgb="FFFFFFFF"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF356B59"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9E2E3"/></left><right style="thin"><color rgb="FFD9E2E3"/></right><top style="thin"><color rgb="FFD9E2E3"/></top><bottom style="thin"><color rgb="FFD9E2E3"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function sheetName(string $value, int $number): string
    {
        $value = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $value) ?? '';
        $value = trim($value);

        return mb_substr($value !== '' ? $value : 'Sheet ' . $number, 0, 31);
    }

    private static function coreProperties(string $title = 'Rekap pemeriksaan klinik'): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>' . self::xml($title) . '</dc:title><dc:creator>Basmalah Medika</dc:creator></cp:coreProperties>';
    }

    private static function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Basmalah Medika</Application></Properties>';
    }
}
