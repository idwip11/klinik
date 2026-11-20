<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string) $error . PHP_EOL);
    exit(1);
});

function pdfAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$base = [
    'id' => 999999, 'patient_id' => 999999, 'examination_type' => 'mcu_general',
    'status' => 'finalized', 'lab_number' => 'UJI-PDF-2026',
    'full_name' => 'Pasien sintetis PDF <uji> & keluarga', 'medical_record_number' => 'UJI-0001',
    'sex' => 'P', 'birth_place' => 'Malang', 'birth_date' => '1997-01-04',
    'examined_on' => '2026-10-20', 'result_date' => '2026-10-21', 'room' => 'Ruang uji',
    'address' => 'Alamat sintetis untuk pengujian PDF, bukan data pasien nyata.',
    'insurance_type' => 'Umum', 'created_at' => '2026-10-20 00:30:00',
    'doctor_name' => 'dr. Contoh Pengujian, Sp.PD <uji>',
    'blood_pressure_systolic' => '110', 'blood_pressure_diastolic' => '70',
    'pulse_rate' => '80', 'height_cm' => '160', 'weight_kg' => '55', 'temperature_c' => '36.5',
];
$panels = ExaminationPanelCatalogRepository::all();
$flags = [];
foreach ($panels as &$panel) {
    foreach ($panel['parameters'] as &$parameter) {
        $code = (string) $parameter['code'];
        $value = $parameter['result_type'] === 'numeric' ? '12.3' : ($parameter['options'][0] ?? 'Negatif');
        if ($code === 'ANC_HGB') $value = '11.8';
        if ($code === 'ANC_BLOOD_GROUP') $value = 'B / Rh (+)';
        if ($code === 'URINE_UROBILINOGEN') $value = 'Normal';
        if ($code === 'WIDAL_AO') $value = '(+) 1/160';
        $parameter['value'] = $value;
        if ($parameter['legacy_field'] !== null) {
            $base[$parameter['legacy_field']] = $value;
        }
        $range = ReferenceRangeRepository::findApplicable($code, 'P', 10950, 30);
        $requiresReference = ReferenceEvaluator::requiresReference($code);
        $flags[] = [
            'test_code' => $code, 'display_name' => $parameter['label'], 'raw_value' => $value,
            'section' => $panel['code'] === 'urinalysis_complete' || str_starts_with($code, 'ANC_URINE_') ? 'urine' : 'blood',
            'unit' => $parameter['unit'], 'reference_sex' => $range === false ? null : $range['sex'],
            'reference_text' => !$requiresReference ? '' : ($range === false ? 'Belum dikonfigurasi' : ReferenceEvaluator::referenceText($range)),
            'interpretation' => !$requiresReference || $range === false ? 'not_assessed' : ReferenceEvaluator::interpretValue($parameter['result_type'] === 'numeric' ? 'numeric' : 'qualitative', $value, $range),
        ];
    }
    unset($parameter);
}
unset($panel);
$drugFlags = [];
foreach (['AMPHETAMINE', 'METHAMPHETAMINE', 'MORPHINE', 'MARIJUANA', 'BENZODIAZEPINE', 'COCAINE'] as $name) {
    $base['drug_' . strtolower($name)] = 'Negatif';
    $drugFlags[] = ['test_code' => 'DRUG_' . $name, 'display_name' => $name, 'raw_value' => 'Negatif', 'section' => 'drug', 'unit' => null, 'reference_sex' => 'ALL', 'reference_text' => 'Negatif', 'interpretation' => 'normal'];
}
$ancPanel = array_values(array_filter($panels, static fn(array $panel): bool => $panel['code'] === 'anc_ibu_hamil'));
$cases = [
    'dl-dll' => [$base, $flags, 'dl-dll', $panels],
    'panel-print' => [$base, $flags, 'panel-print', $panels],
    'anc' => [$base, $flags, 'panel-print', $ancPanel],
    'blood' => [$base, $flags, 'blood_general', []],
    'urine' => [$base, $flags, 'urine_complete', []],
    'newborn' => [array_merge($base, ['examination_type' => 'newborn_blood', 'birth_date' => '2026-10-18']), array_slice($flags, 0, 5), 'newborn_blood', []],
    'drug' => [array_merge($base, ['examination_type' => 'drug_screen']), $drugFlags, 'drug_screen', []],
];
if (isset($argv[1]) && str_starts_with($argv[1], '--excel=')) {
    $case = substr($argv[1], 8);
    pdfAssert(isset($cases[$case]), 'Kasus Excel tidak ditemukan.');
    [$examination, $exportFlags, $format, $panelForm] = $cases[$case];
    if ($format === 'dl-dll') ExaminationExcelExporter::downloadDlDll($examination, $exportFlags, $panelForm);
    if ($format === 'panel-print') ExaminationExcelExporter::downloadPanelPrint($examination, $exportFlags, $panelForm);
    ExaminationExcelExporter::downloadPreview($examination, $exportFlags, $format);
}
$exportCase = isset($argv[1]) && str_starts_with($argv[1], '--export=') ? substr($argv[1], 9) : null;
if ($exportCase !== null) {
    pdfAssert(isset($cases[$exportCase]), 'Kasus PDF tidak ditemukan.');
    echo ExaminationPdfExporter::build(...$cases[$exportCase]);
    exit;
}

$beforePrints = (int) Database::connection()->query('SELECT COUNT(*) FROM print_logs')->fetchColumn();
foreach ($cases as $name => $arguments) {
    $html = ExaminationPdfExporter::html(...$arguments);
    pdfAssert(!str_contains($html, 'Download Excel') && !str_contains($html, 'Download PDF'), "$name: toolbar ikut masuk PDF.");
    pdfAssert(!str_contains($html, '<script') && !str_contains($html, '_token'), "$name: script atau token CSRF ikut masuk PDF.");
    pdfAssert(!str_contains($html, '<dl') && str_contains($html, 'pdf-patient-summary'), "$name: identitas harus memakai layout kompatibel PDF.");
    pdfAssert(str_contains($html, '&lt;uji&gt;') && str_contains($html, '&amp; keluarga'), "$name: data identitas tidak di-escape.");
    pdfAssert(str_contains($html, '21 Oktober 2026'), "$name: tanggal hasil tidak mengikuti tanggal tersimpan.");
    pdfAssert(!str_contains($html, 'dr. Contoh Pengujian') && !str_contains($html, '>Dokter<'), "$name: identitas PDF masih memuat dokter.");
    $identityDocument = new DOMDocument();
    $identityDocument->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $identityXpath = new DOMXPath($identityDocument);
    foreach ($identityXpath->query('//table[@class="pdf-patient-summary"]/tr') as $row) {
        $columns = 0;
        foreach ($identityXpath->query('./td', $row) as $cell) {
            $columns += max(1, (int) $cell->getAttribute('colspan'));
        }
        pdfAssert($columns === 4, "$name: baris identitas PDF tidak mengisi lebar tabel setelah dokter dihapus.");
    }
    pdfAssert(str_contains($html, View::formatAge($arguments[0]['birth_date'], $arguments[0]['examined_on'])), "$name: umur tidak sesuai tanggal pemeriksaan.");
    pdfAssert(!str_contains($html, 'Tempat, tanggal lahir') && !str_contains($html, 'Tanggal Lahir'), "$name: identitas masih memakai tanggal lahir.");
    $pdf = ExaminationPdfExporter::build(...$arguments);
    pdfAssert(str_starts_with($pdf, '%PDF-') && str_contains($pdf, '%%EOF'), "$name: berkas bukan PDF valid.");
    pdfAssert(!str_contains($pdf, '/JavaScript'), "$name: PDF tidak boleh berisi JavaScript.");
}
$afterPrints = (int) Database::connection()->query('SELECT COUNT(*) FROM print_logs')->fetchColumn();
pdfAssert($beforePrints === $afterPrints, 'Membuat PDF tidak boleh menambah catatan cetak fisik.');

$snapshotFlags = [['test_code' => 'URINE_PROTEIN', 'display_name' => 'Protein Urine', 'raw_value' => 'Negatif', 'unit' => null, 'section' => 'urine', 'reference_text' => 'Rujukan snapshot versi lama', 'reference_sex' => 'ALL', 'interpretation' => 'normal']];
pdfAssert(str_contains(ExaminationPdfExporter::html($base, $snapshotFlags, 'urine_complete'), 'Rujukan snapshot versi lama'), 'PDF harus mempertahankan rujukan snapshot, bukan menilai ulang.');
foreach ([
    [$base, '../../etc/passwd', []],
    [array_merge($base, ['examination_type' => 'drug_screen']), 'dl-dll', []],
    [array_merge($base, ['examination_type' => 'newborn_blood']), 'drug_screen', []],
    [$base, 'panel-print', []],
    [array_merge($base, ['status' => 'invalid']), 'blood_general', []],
] as [$examination, $format, $panelForm]) {
    try {
        ExaminationPdfExporter::validateFormat($examination, $format, $panelForm);
        throw new RuntimeException('Format yang tidak sesuai diterima.');
    } catch (ValidationException) {
        // Expected.
    }
}
$filename = ExaminationPdfExporter::filename(array_merge($base, ['lab_number' => "../../x\r\nInjected: yes"]), 'dl-dll');
pdfAssert(preg_match('/^[A-Za-z0-9_-]+\.pdf$/', $filename) === 1, 'Nama unduhan harus aman untuk header HTTP.');
echo "PDF regression: OK; 7 format/kasus, snapshot, escaping, validasi, dan log cetak. Tidak mengubah hasil pasien.\n";
