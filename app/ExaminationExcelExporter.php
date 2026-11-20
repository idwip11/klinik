<?php

declare(strict_types=1);

/**
 * Menghasilkan file Excel (.xlsx) untuk hasil pemeriksaan per-pasien.
 * Menggunakan mekanisme ZipArchive yang sama dengan ReportExcelExporter
 * tanpa dependensi pustaka tambahan (pure PHP).
 */
final class ExaminationExcelExporter
{
    /**
     * Download Excel untuk halaman DL/DLL.
     *
     * @param array<string, mixed> $examination
     * @param list<array<string, mixed>> $flags
     * @param list<array{name:string,parameters:list<array<string,mixed>>}> $panelForm
     */
    public static function downloadDlDll(array $examination, array $flags, array $panelForm): never
    {
        $sexLabels = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
        $interpretationLabels = ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'abnormal' => 'Abnormal', 'not_assessed' => 'Belum dinilai'];

        $flagsByCode = [];
        foreach ($flags as $flag) {
            $flagsByCode[(string) $flag['test_code']] = $flag;
        }

        // Build patient info rows
        $patientInfo = self::patientInfo($examination);

        // Build result rows
        $hematologyRows = [
            ['code' => 'HGB', 'field' => 'hgb', 'label' => 'Hemoglobin (HGB)', 'unit' => 'g/dL'],
            ['code' => 'HCT', 'field' => 'hct', 'label' => 'Hematokrit (HCT)', 'unit' => '%'],
            ['code' => 'RBC', 'field' => 'rbc', 'label' => 'Eritrosit (RBC)', 'unit' => 'juta/µL'],
            ['code' => 'WBC', 'field' => 'wbc', 'label' => 'Leukosit (WBC)', 'unit' => 'sel/mm³'],
            ['code' => 'PLT', 'field' => 'plt', 'label' => 'Trombosit (PLT)', 'unit' => 'sel/mm³'],
        ];
        $threePartDifferentialRows = [
            ['code' => 'LYMPH_PCT', 'field' => 'lymph_pct', 'label' => 'LYMPH%', 'unit' => '%'],
            ['code' => 'MID_PCT', 'field' => 'mid_pct', 'label' => 'MID%', 'unit' => '%'],
            ['code' => 'GRAN_PCT', 'field' => 'gran_pct', 'label' => 'GRAN%', 'unit' => '%'],
        ];
        $erythrocyteIndexRows = [
            ['code' => 'MCV', 'field' => 'mcv', 'label' => 'MCV', 'unit' => 'fL'],
            ['code' => 'MCH', 'field' => 'mch', 'label' => 'MCH', 'unit' => 'pg'],
            ['code' => 'MCHC', 'field' => 'mchc', 'label' => 'MCHC', 'unit' => 'g/dL'],
        ];
        $fivePartDifferentialRows = [
            ['code' => 'EOSINOPHIL', 'field' => 'eosinophil', 'label' => 'Eosinofil', 'unit' => '%'],
            ['code' => 'BASOPHIL', 'field' => 'basophil', 'label' => 'Basofil', 'unit' => '%'],
            ['code' => 'NEUTROPHIL', 'field' => 'neutrophil', 'label' => 'Neutrofil', 'unit' => '%'],
            ['code' => 'LYMPHOCYTE', 'field' => 'lymphocyte', 'label' => 'Limfosit', 'unit' => '%'],
            ['code' => 'MONOCYTE', 'field' => 'monocyte', 'label' => 'Monosit', 'unit' => '%'],
        ];
        $chemistryRows = [
            ['code' => 'GDS', 'field' => 'gds', 'label' => 'Glukosa Darah Sewaktu', 'unit' => 'mg/dL'],
            ['code' => 'FBS', 'field' => 'fasting_glucose', 'label' => 'Glukosa Darah Puasa', 'unit' => 'mg/dL'],
            ['code' => 'GLUCOSE_2H_PP', 'field' => 'glucose_2h_pp', 'label' => 'Glukosa Darah 2 Jam PP', 'unit' => 'mg/dL'],
            ['code' => 'UREA', 'field' => 'urea', 'label' => 'Ureum', 'unit' => 'mg/dL'],
            ['code' => 'CREATININE', 'field' => 'creatinine', 'label' => 'Kreatinin', 'unit' => 'mg/dL'],
            ['code' => 'SGOT', 'field' => 'sgot', 'label' => 'AST (SGOT)', 'unit' => 'U/L'],
            ['code' => 'SGPT', 'field' => 'sgpt', 'label' => 'ALT (SGPT)', 'unit' => 'U/L'],
            ['code' => 'CHOL', 'field' => 'cholesterol', 'label' => 'Kolesterol', 'unit' => 'mg/dL'],
            ['code' => 'UA', 'field' => 'uric_acid', 'label' => 'Asam Urat', 'unit' => 'mg/dL'],
        ];

        $legacyTestCodes = [];
        foreach ([$hematologyRows, $threePartDifferentialRows, $erythrocyteIndexRows, $fivePartDifferentialRows, $chemistryRows] as $rows) {
            foreach ($rows as $row) {
                $legacyTestCodes[$row['code']] = true;
            }
        }

        $sections = [
            ['title' => 'HEMATOLOGI', 'rows' => $hematologyRows],
            ['title' => 'HITUNG JENIS LEUKOSIT (3 BAGIAN)', 'rows' => $threePartDifferentialRows],
            ['title' => 'INDEKS ERITROSIT', 'rows' => $erythrocyteIndexRows],
            ['title' => 'HITUNG JENIS LEUKOSIT (5 BAGIAN)', 'rows' => $fivePartDifferentialRows],
            ['title' => 'KIMIA KLINIK', 'rows' => $chemistryRows],
        ];

        $dataRows = [];
        foreach ($sections as $section) {
            $sectionHasData = false;
            foreach ($section['rows'] as $row) {
                $flag = $flagsByCode[$row['code']] ?? null;
                $value = $flag['raw_value'] ?? $examination[$row['field']] ?? null;
                if ($value !== null && trim((string) $value) !== '') {
                    $sectionHasData = true;
                    break;
                }
            }
            if (!$sectionHasData) {
                continue;
            }
            $dataRows[] = [$section['title'], '', '', '', ''];
            foreach ($section['rows'] as $row) {
                $flag = $flagsByCode[$row['code']] ?? null;
                $value = $flag['raw_value'] ?? $examination[$row['field']] ?? null;
                if ($value === null || trim((string) $value) === '') {
                    continue;
                }
                $referenceText = ReferenceEvaluator::referenceTextForSex($flag['reference_text'] ?? '—', $examination['sex'] ?? null);
                $interpretation = (string) ($flag['interpretation'] ?? 'not_assessed');
                $dataRows[] = [
                    $row['label'],
                    self::formatNumber((string) $value),
                    $flag['unit'] ?? $row['unit'],
                    $referenceText ?: '—',
                    $interpretationLabels[$interpretation] ?? 'Belum dinilai',
                ];
            }
        }

        // Additional panels
        $allFlagsByCode = [];
        foreach ($flags as $flag) {
            $allFlagsByCode[(string) $flag['test_code']] = $flag;
        }
        foreach ($panelForm as $panel) {
            $parameters = array_values(array_filter($panel['parameters'], static function (array $parameter) use ($legacyTestCodes): bool {
                $value = $parameter['value'] ?? null;
                return !isset($legacyTestCodes[$parameter['code']])
                    && $value !== null
                    && trim((string) $value) !== '';
            }));
            if ($parameters === []) {
                continue;
            }
            $dataRows[] = [mb_strtoupper((string) $panel['name']), '', '', '', ''];
            foreach ($parameters as $parameter) {
                $flag = $allFlagsByCode[(string) $parameter['code']] ?? null;
                $value = (string) $parameter['value'];
                $referenceText = $flag === null ? '—' : ReferenceEvaluator::referenceTextForSex($flag['reference_text'] ?? '', $examination['sex'] ?? null);
                $interpretation = (string) ($flag['interpretation'] ?? 'not_assessed');
                $isAncBloodGroup = $parameter['code'] === 'ANC_BLOOD_GROUP';
                $dataRows[] = [
                    $parameter['label'],
                    $parameter['code'] === 'ANC_HGB' ? View::formatNumber($value) : ($parameter['result_type'] === 'numeric' ? self::formatNumber($value) : $value),
                    $flag['unit'] ?? $parameter['unit'] ?? '—',
                    $isAncBloodGroup ? '' : ($referenceText ?: '—'),
                    $isAncBloodGroup ? '' : ($interpretationLabels[$interpretation] ?? 'Belum dinilai'),
                ];
            }
        }

        $filename = 'hasil-lab-' . ($examination['medical_record_number'] ?? 'unknown') . '-' . ($examination['examined_on'] ?? date('Y-m-d')) . '.xlsx';

        self::generateExcel($filename, $patientInfo, $dataRows, 'Hasil Pemeriksaan Laboratorium');
    }

    /**
     * Download Excel untuk halaman panel print.
     *
     * @param array<string, mixed> $examination
     * @param list<array<string, mixed>> $flags
     * @param list<array{name:string,parameters:list<array<string,mixed>>}> $panelForm
     */
    public static function downloadPanelPrint(array $examination, array $flags, array $panelForm): never
    {
        $sexLabels = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
        $interpretationLabels = ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'abnormal' => 'Abnormal', 'not_assessed' => 'Belum dinilai'];
        $referenceNotApplicableCodes = ['BLOOD_GROUP', 'RHESUS', 'ANC_BLOOD_GROUP'];

        $flagsByCode = [];
        foreach ($flags as $flag) {
            $flagsByCode[(string) $flag['test_code']] = $flag;
        }

        $patientInfo = self::patientInfo($examination);

        $dataRows = [];
        foreach ($panelForm as $panel) {
            $panelParameters = array_values(array_filter($panel['parameters'], static function (array $parameter): bool {
                $value = $parameter['value'] ?? null;
                return $value !== null && trim((string) $value) !== '';
            }));
            if ($panelParameters === []) {
                continue;
            }
            $dataRows[] = [mb_strtoupper((string) $panel['name']), '', '', '', ''];
            foreach ($panelParameters as $parameter) {
                $flag = $flagsByCode[(string) $parameter['code']] ?? null;
                $value = $parameter['value'];
                $hasValue = $value !== null && trim((string) $value) !== '';
                $reference = $flag === null ? '—' : ReferenceEvaluator::referenceTextForSex($flag['reference_text'] ?? '', $examination['sex'] ?? null);
                if ((string) $parameter['code'] === 'HBSAG_ECLIA') {
                    $reference = '≤ 0,9: Non Reaktif / > 1: Reaktif';
                }
                if (in_array((string) $parameter['code'], $referenceNotApplicableCodes, true)) {
                    $reference = '';
                }
                $interpretation = $flag['interpretation'] ?? 'not_assessed';
                $dataRows[] = [
                    $parameter['label'],
                    $hasValue ? ($parameter['code'] === 'ANC_HGB' ? View::formatNumber((string) $value) : self::formatNumber((string) $value)) : '—',
                    $flag['unit'] ?? $parameter['unit'] ?? '—',
                    $parameter['code'] === 'ANC_BLOOD_GROUP' ? '' : ($reference ?: '—'),
                    $parameter['code'] === 'ANC_BLOOD_GROUP' ? '' : ($interpretationLabels[(string) $interpretation] ?? 'Belum dinilai'),
                ];
            }
        }

        $filename = 'hasil-panel-' . ($examination['medical_record_number'] ?? 'unknown') . '-' . ($examination['examined_on'] ?? date('Y-m-d')) . '.xlsx';

        self::generateExcel($filename, $patientInfo, $dataRows, 'Hasil Panel Laboratorium');
    }

    /**
     * Download Excel untuk halaman preview (blood/urine/drug/newborn).
     *
     * @param array<string, mixed> $examination
     * @param list<array<string, mixed>> $flags
     * @param string $documentType
     */
    public static function downloadPreview(array $examination, array $flags, string $documentType): never
    {
        $sexLabels = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
        $interpretationLabels = ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'abnormal' => 'Abnormal', 'not_assessed' => 'Belum dinilai'];
        $documentLabels = ['blood_general' => 'Hasil Laboratorium Darah', 'urine_complete' => 'Hasil Urine Lengkap', 'newborn_blood' => 'Hasil Laboratorium Darah Bayi', 'drug_screen' => 'Hasil Skrining Narkoba'];
        $section = ['urine_complete' => 'urine', 'drug_screen' => 'drug'][$documentType] ?? 'blood';

        $visibleFlags = array_values(array_filter($flags, static fn(array $flag): bool => $flag['section'] === $section));

        $patientInfo = self::patientInfo($examination);

        $dataRows = [];
        foreach ($visibleFlags as $flag) {
            $referenceText = ReferenceEvaluator::referenceTextForSex($flag['reference_text'] ?? '—', $examination['sex'] ?? null);
            $dataRows[] = [
                $flag['display_name'],
                self::formatNumber($flag['raw_value']),
                $flag['unit'],
                $referenceText ?: '—',
                $interpretationLabels[$flag['interpretation']] ?? $flag['interpretation'],
            ];
        }

        $typeSlug = str_replace('_', '-', $documentType);
        $filename = 'hasil-' . $typeSlug . '-' . ($examination['medical_record_number'] ?? 'unknown') . '-' . ($examination['examined_on'] ?? date('Y-m-d')) . '.xlsx';

        self::generateExcel($filename, $patientInfo, $dataRows, $documentLabels[$documentType] ?? 'Hasil Pemeriksaan');
    }

    // ─── internal ───────────────────────────────────────────────

    /** @param array<string,mixed> $examination @return list<array{0:string,1:string}> */
    public static function patientInfo(array $examination): array
    {
        return [
            ['No. RM', (string) ($examination['medical_record_number'] ?? '')],
            ['No. Laboratorium', (string) ($examination['lab_number'] ?? '')],
            ['Nama Pasien', (string) ($examination['full_name'] ?? '')],
            ['Jenis Kelamin', ['L' => 'Laki-laki', 'P' => 'Perempuan'][$examination['sex'] ?? ''] ?? '—'],
            ['Umur', View::formatAge($examination['birth_date'] ?? null, $examination['examined_on'] ?? null)],
            ['Tanggal Periksa', View::formatDate($examination['examined_on'] ?? null)],
            ['Ruangan', (string) (($examination['room'] ?? '') ?: '—')],
            ['Asuransi / penjamin', (string) (($examination['insurance_type'] ?? '') ?: '—')],
            ['Alamat', (string) (($examination['address'] ?? '') ?: '—')],
        ];
    }

    /**
     * @param list<array{0:string,1:string}> $patientInfo
     * @param list<list<string>> $dataRows
     */
    private static function generateExcel(string $filename, array $patientInfo, array $dataRows, string $title): never
    {
        $path = tempnam(sys_get_temp_dir(), 'clinic-exam-');
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
            $zip->addFromString('xl/worksheets/sheet1.xml', self::worksheet($patientInfo, $dataRows, $title));
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

    private static function formatDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $date->format('d/m/Y') : $value;
    }

    private static function formatNumber(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !is_numeric(str_replace(',', '.', str_replace('.', '', $value)))) {
            return $value;
        }
        return $value;
    }

    private static function xmlEncode(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param list<array{0:string,1:string}> $patientInfo
     * @param list<list<string>> $dataRows
     */
    private static function worksheet(array $patientInfo, array $dataRows, string $title): string
    {
        $rowIndex = 1;
        $sheetRows = '';
        $patientMerges = [];

        // Title row
        $sheetRows .= '<row r="' . $rowIndex . '">';
        $sheetRows .= '<c r="A' . $rowIndex . '" s="2" t="inlineStr"><is><t>' . self::xmlEncode($title) . '</t></is></c>';
        $sheetRows .= '</row>';
        $rowIndex++;

        // Clinic name
        $clinicName = defined('ROOT_PATH') && class_exists('Env') ? Env::get('CLINIC_NAME', '') : '';
        if ($clinicName !== '') {
            $sheetRows .= '<row r="' . $rowIndex . '">';
            $sheetRows .= '<c r="A' . $rowIndex . '" s="0" t="inlineStr"><is><t>' . self::xmlEncode($clinicName) . '</t></is></c>';
            $sheetRows .= '</row>';
            $rowIndex++;
        }

        // Empty row
        $rowIndex++;

        // Patient info
        foreach ($patientInfo as $info) {
            $sheetRows .= '<row r="' . $rowIndex . '">';
            $sheetRows .= '<c r="A' . $rowIndex . '" s="4" t="inlineStr"><is><t>' . self::xmlEncode($info[0]) . '</t></is></c>';
            $sheetRows .= '<c r="B' . $rowIndex . '" s="5" t="inlineStr"><is><t>' . self::xmlEncode($info[1]) . '</t></is></c>';
            $sheetRows .= '</row>';
            $patientMerges[] = '<mergeCell ref="B' . $rowIndex . ':E' . $rowIndex . '"/>';
            $rowIndex++;
        }

        // Empty row
        $rowIndex++;

        // Table headers
        $headers = ['Pemeriksaan', 'Hasil', 'Satuan', 'Nilai Rujukan', 'Keterangan'];
        $columns = ['A', 'B', 'C', 'D', 'E'];
        $sheetRows .= '<row r="' . $rowIndex . '">';
        foreach ($headers as $i => $header) {
            $sheetRows .= '<c r="' . $columns[$i] . $rowIndex . '" s="1" t="inlineStr"><is><t>' . self::xmlEncode($header) . '</t></is></c>';
        }
        $sheetRows .= '</row>';
        $rowIndex++;

        // Data rows
        foreach ($dataRows as $row) {
            $sheetRows .= '<row r="' . $rowIndex . '">';
            $isSectionHeader = ($row[1] ?? '') === '' && ($row[2] ?? '') === '' && ($row[3] ?? '') === '' && ($row[4] ?? '') === '';
            foreach ($row as $i => $cell) {
                $col = $columns[$i] ?? 'A';
                $style = ($isSectionHeader && $i === 0) ? '2' : '0';
                $sheetRows .= '<c r="' . $col . $rowIndex . '" s="' . $style . '" t="inlineStr"><is><t>' . self::xmlEncode((string) $cell) . '</t></is></c>';
            }
            $sheetRows .= '</row>';
            $rowIndex++;
        }

        $colWidths = '<cols>'
            . '<col min="1" max="1" width="30" customWidth="1"/>'
            . '<col min="2" max="2" width="15" customWidth="1"/>'
            . '<col min="3" max="3" width="15" customWidth="1"/>'
            . '<col min="4" max="4" width="25" customWidth="1"/>'
            . '<col min="5" max="5" width="18" customWidth="1"/>'
            . '</cols>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $colWidths
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . '<mergeCells count="' . count($patientMerges) . '">' . implode('', $patientMerges) . '</mergeCells>'
            . '</worksheet>';
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private static function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Hasil" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="5">'
            . '<font><sz val="10"/><name val="Arial"/></font>'
            . '<font><b/><sz val="10"/><name val="Arial"/></font>'
            . '<font><b/><sz val="12"/><name val="Arial"/></font>'
            . '<font><sz val="9.75"/><name val="Arial"/></font>'
            . '<font><b/><sz val="9.75"/><name val="Arial"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1A7A8A"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color indexed="64"/></left><right style="thin"><color indexed="64"/></right><top style="thin"><color indexed="64"/></top><bottom style="thin"><color indexed="64"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'        // s=0 normal with border
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>'  // s=1 header
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'          // s=2 title/section bold
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'          // s=3 label bold
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="top"/></xf>' // s=4 identity 13px equivalent
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="top" wrapText="1"/></xf>' // s=5 identity value
            . '</cellXfs>'
            . '</styleSheet>';
    }

    private static function coreProperties(string $title): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::xmlEncode($title) . '</dc:title>'
            . '<dc:creator>Klinik Basmalah Medika</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '</cp:coreProperties>';
    }

    private static function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
            . '<Application>Klinik Basmalah Medika</Application>'
            . '</Properties>';
    }
}
