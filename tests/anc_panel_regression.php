<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

function ancAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Database::connection();
$panels = ExaminationPanelCatalogRepository::all();
$catalog = array_column($panels, null, 'code');
$anc = $catalog['anc_ibu_hamil'];
$codes = ['ANC_HGB', 'ANC_BLOOD_GROUP', 'ANC_HBSAG_RAPID', 'ANC_HIV_RAPID', 'ANC_SYPHILIS_RAPID', 'ANC_GDS', 'ANC_URINE_ALBUMIN', 'ANC_URINE_REDUCTION'];
ancAssert(array_column($anc['parameters'], 'code') === $codes, 'ANC harus memiliki delapan parameter sesuai urutan lembar klinik.');
ancAssert($anc['required_sex'] === 'P' && !$anc['is_always_included'], 'ANC harus opsional dan khusus perempuan.');
ancAssert($catalog['widal']['is_always_included'], 'Widal harus tetap selalu disertakan.');
foreach (['L', null] as $sex) {
    try {
        ExaminationPanelCatalogRepository::selectionFromInput(['anc_ibu_hamil'], $pdo, $sex);
        throw new RuntimeException('ANC masih bisa dipilih pada pasien bukan perempuan.');
    } catch (ValidationException) {
        // Expected.
    }
}
$selection = ExaminationPanelCatalogRepository::selectionFromInput(['anc_ibu_hamil'], $pdo, 'P');
ancAssert(array_column($selection, 'code') === ['widal', 'anc_ibu_hamil'], 'ANC yang dipilih harus tetap menyertakan Widal.');
$expectedReferences = [
    'ANC_HGB' => '11,4 – 15,1',
    'ANC_HBSAG_RAPID' => 'Non Reaktif',
    'ANC_HIV_RAPID' => 'Non Reaktif',
    'ANC_SYPHILIS_RAPID' => 'Negatif',
    'ANC_GDS' => '< 200',
    'ANC_URINE_ALBUMIN' => 'Negatif',
    'ANC_URINE_REDUCTION' => 'Negatif',
];
foreach ($expectedReferences as $code => $expected) {
    $range = ReferenceRangeRepository::findApplicable($code, 'P', null);
    ancAssert($range !== false && $range['sex'] === 'P', 'Nilai normal ANC harus khusus perempuan.');
    ancAssert(ReferenceEvaluator::referenceText($range) === $expected, 'Nilai normal ANC harus sama dengan lembar klinik.');
    ancAssert(ReferenceRangeRepository::findApplicable($code, 'L', null) === false, 'Nilai normal ANC tidak boleh diterapkan pada laki-laki.');
    ancAssert(ReferenceRangeRepository::findApplicable($code, null, null) === false, 'Nilai normal ANC tidak boleh diterapkan tanpa jenis kelamin.');
}
ancAssert(ReferenceRangeRepository::findApplicable('ANC_BLOOD_GROUP', 'P', null) === false, 'Golongan darah/Rhesus tidak memiliki nilai normal.');
$range = ReferenceRangeRepository::findApplicable('ANC_HGB', 'P', null);
foreach (['11.3' => 'low', '11.4' => 'normal', '11.8' => 'normal', '15.1' => 'normal', '15.2' => 'high'] as $value => $interpretation) {
    ancAssert(ReferenceEvaluator::interpretValue('numeric', $value, $range) === $interpretation, 'Penilaian batas Hemoglobin ANC tidak sesuai.');
}

// The creation form must disable ANC for male/unknown sex and enable it for females.
foreach (['P' => false, 'L' => true, '' => true] as $sex => $disabled) {
    $patient = ['id' => 1, 'full_name' => 'Pasien uji ANC', 'medical_record_number' => 'UJI', 'sex' => $sex];
    $input = [];
    ob_start();
    require ROOT_PATH . '/app/views/examinations/create.php';
    $html = (string) ob_get_clean();
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $checkbox = (new DOMXPath($document))->query('//input[@name="panel_codes[]"][@value="anc_ibu_hamil"]')->item(0);
    ancAssert($checkbox instanceof DOMElement && $checkbox->hasAttribute('disabled') === $disabled, 'Checklist ANC harus mengikuti jenis kelamin pasien.');
}

$userId = (int) $pdo->query('SELECT id FROM users WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
ancAssert($userId > 0, 'Pengguna aktif diperlukan untuk transaksi uji.');
$pdo->beginTransaction();
try {
    $insertPatient = $pdo->prepare('INSERT INTO patients (medical_record_number, full_name, sex, created_at, updated_at) VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())');
    $insertPatient->execute(['__ANC_P_' . bin2hex(random_bytes(5)), 'Pasien perempuan uji ANC', 'P']);
    $femaleId = (int) $pdo->lastInsertId();
    $insertPatient->execute(['__ANC_L_' . bin2hex(random_bytes(5)), 'Pasien laki-laki uji ANC', 'L']);
    $maleId = (int) $pdo->lastInsertId();
    try {
        ExaminationRepository::create([
            'patient_id' => $maleId, 'sex' => 'P', 'lab_number' => '__ANC_REJECT',
            'examined_on' => '2026-10-20', 'examined_time' => '08:00', 'room' => 'Uji',
            'examination_type' => 'mcu_general', 'panel_codes' => ['anc_ibu_hamil'],
        ], $userId);
        throw new RuntimeException('Pembuatan ANC menerima jenis kelamin yang dipalsukan pada formulir.');
    } catch (ValidationException) {
        // Expected before any examination transaction is opened.
    }
    foreach (['L', 'ALL'] as $sex) {
        try {
            ReferenceRangeRepository::createDraft(['test_code' => 'ANC_HGB', 'sex' => $sex], $userId);
            throw new RuntimeException('Konfigurasi nilai normal ANC masih menerima sasaran selain perempuan.');
        } catch (ValidationException) {
            // Expected.
        }
    }
    $pdo->prepare('INSERT INTO examinations (patient_id, lab_number, examination_type, status, examined_on, created_by, updated_by, created_at, updated_at) VALUES (?, ?, "mcu_general", "draft", "2026-10-20", ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')
        ->execute([$femaleId, '__ANC_TEST_' . bin2hex(random_bytes(5)), $userId, $userId]);
    $id = (int) $pdo->lastInsertId();
    ExaminationPanelCatalogRepository::storeSelections($pdo, $id, $selection);
    $panelForm = ExaminationPanelCatalogRepository::formForExamination($id);
    ancAssert(ExaminationParameterResultRepository::normalize($panelForm, [])['rows'] === [], 'Hasil ANC tidak boleh terisi otomatis dari contoh atau nilai normal.');
    $values = ['ANC_HGB' => '11,8', 'ANC_BLOOD_GROUP' => 'B / Rh (+)', 'ANC_HBSAG_RAPID' => 'Non Reaktif', 'ANC_HIV_RAPID' => 'Non Reaktif', 'ANC_SYPHILIS_RAPID' => 'Negatif', 'ANC_GDS' => '120', 'ANC_URINE_ALBUMIN' => 'Negatif', 'ANC_URINE_REDUCTION' => 'Negatif'];
    $normalized = ExaminationParameterResultRepository::normalize($panelForm, $values);
    ExaminationParameterResultRepository::replace($pdo, $id, $normalized['rows']);
    $panelForm = ExaminationPanelCatalogRepository::formForExamination($id);
    $actual = array_column($panelForm[1]['parameters'], 'value', 'code');
    ancAssert($actual['ANC_HGB'] === '11.8' && $actual['ANC_BLOOD_GROUP'] === 'B / Rh (+)', 'Hasil ANC tidak tersimpan dengan benar.');
    $examination = ExaminationRepository::find($id);
    $flags = ReferenceEvaluator::evaluate($examination);
    $byCode = array_column($flags, null, 'test_code');
    foreach ($expectedReferences as $code => $normal) {
        ancAssert($byCode[$code]['reference_sex'] === 'P' && $byCode[$code]['reference_text'] === $normal, 'Evaluasi ANC harus memakai rujukan perempuan.');
        ancAssert($byCode[$code]['interpretation'] === 'normal', 'Contoh hasil normal dinilai tidak sesuai.');
    }
    ancAssert($byCode['ANC_URINE_ALBUMIN']['section'] === 'urine', 'Albumin Urine harus dicatat sebagai hasil urine.');
    foreach (['panel_print', 'dl_dll'] as $template) {
        ob_start();
        require ROOT_PATH . '/app/views/examinations/' . $template . '.php';
        $printHtml = (string) ob_get_clean();
        ancAssert(str_contains($printHtml, 'ANC IBU HAMIL'), 'Panel ANC hilang pada cetak.');
        ancAssert(str_contains($printHtml, '11,8') && str_contains($printHtml, '11,4 – 15,1'), 'Cetak harus membedakan hasil Hemoglobin dan nilai normalnya.');
        ancAssert(str_contains($printHtml, 'B / Rh (+)') && str_contains($printHtml, 'Reduksi Urine'), 'Parameter ANC hilang pada cetak.');
    }
    if (in_array($argv[1] ?? '', ['--export-panel', '--export-dl'], true)) {
        register_shutdown_function(static function () use ($pdo): void {
            if ($pdo->inTransaction()) $pdo->rollBack();
        });
        if ($argv[1] === '--export-dl') {
            ExaminationExcelExporter::downloadDlDll($examination, $flags, $panelForm);
        }
        ExaminationExcelExporter::downloadPanelPrint($examination, $flags, $panelForm);
    }
    // Saving must also reject ANC if patient sex is subsequently changed.
    $pdo->prepare('UPDATE patients SET sex = "L" WHERE id = ?')->execute([$femaleId]);
    try {
        ExaminationRepository::saveResults($id, ['parameter_values' => $values], $userId);
        throw new RuntimeException('Penyimpanan ANC masih menerima pasien bukan perempuan.');
    } catch (ValidationException) {
        // Expected.
    }
} finally {
    $pdo->rollBack();
}

echo "ANC panel regression: OK; transaksi uji dibatalkan.\n";
