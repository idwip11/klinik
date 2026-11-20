<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

function widalAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function widalPanel(array $panels): array
{
    return array_values(array_filter($panels, static fn (array $panel): bool => $panel['code'] === 'widal'));
}

function widalFormHtml(array $panelForm): string
{
    ob_start();
    require ROOT_PATH . '/app/views/examinations/forms/panels.php';
    return (string) ob_get_clean();
}

$panels = widalPanel(ExaminationPanelCatalogRepository::all());
widalAssert(count($panels[0]['parameters'] ?? []) === 4, 'Empat parameter Widal harus tersedia.');
foreach ($panels[0]['parameters'] as $parameter) {
    $expectedType = $parameter['code'] === 'WIDAL_O' ? 'text' : 'select';
    widalAssert($parameter['result_type'] === $expectedType, 'Widal O harus dapat diketik; AO, BO, H harus berupa dropdown.');
    widalAssert($parameter['options'] === WidalResultOptions::VALUES, 'Pilihan Widal tidak sesuai.');
}
$blankHtml = widalFormHtml($panels);
widalAssert(substr_count($blankHtml, '<select ') === 3, 'AO, BO, H harus menampilkan dropdown.');
widalAssert(str_contains($blankHtml, 'name="parameter_values[WIDAL_O]" type="text" list="widal-o-options"'), 'Widal O harus memiliki isian manual dengan daftar pilihan.');
widalAssert(!str_contains($blankHtml, 'selected'), 'Hasil kosong tidak boleh otomatis menjadi negatif.');
foreach (WidalResultOptions::VALUES as $option) {
    $input = array_fill_keys(array_keys(WidalResultOptions::FIELDS), $option);
    $normalized = ExaminationParameterResultRepository::normalize($panels, $input);
    widalAssert($normalized['values'] === $input, 'Titer/Negatif harus disimpan persis seperti pilihan.');
}
try {
    ExaminationParameterResultRepository::normalize($panels, ['WIDAL_AO' => '(+) 1/999']);
    throw new RuntimeException('Pilihan baru di luar dropdown masih diterima.');
} catch (ValidationException) {
    // Expected: an arbitrary new result cannot be submitted as a dropdown option.
}
widalAssert(ExaminationParameterResultRepository::normalize($panels, [])['rows'] === [], 'Kosong harus tetap kosong.');
$manual = ExaminationParameterResultRepository::normalize($panels, ['WIDAL_O' => 'Hasil manual 1:40']);
widalAssert($manual['values']['WIDAL_O'] === 'Hasil manual 1:40', 'Widal O harus menerima hasil manual di luar daftar.');
try {
    ExaminationParameterResultRepository::normalize($panels, ['WIDAL_O' => str_repeat('a', 51)]);
    throw new RuntimeException('Widal O tidak boleh melebihi batas penyimpanan 50 karakter.');
} catch (ValidationException) {
    // Expected.
}

$pdo = Database::connection();
$patientId = (int) $pdo->query('SELECT id FROM patients ORDER BY id LIMIT 1')->fetchColumn();
$userId = (int) $pdo->query('SELECT id FROM users WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
widalAssert($patientId > 0 && $userId > 0, 'Diperlukan pasien dan pengguna lokal untuk transaksi uji.');
$pdo->beginTransaction();
try {
    $insert = $pdo->prepare(
        'INSERT INTO examinations
            (patient_id, lab_number, examination_type, status, examined_on, created_by, updated_by, created_at, updated_at)
         VALUES (?, ?, "mcu_general", "draft", CURRENT_DATE(), ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $insert->execute([$patientId, '__WIDAL_TEST_' . bin2hex(random_bytes(6)), $userId, $userId]);
    $id = (int) $pdo->lastInsertId();
    ExaminationPanelCatalogRepository::storeSelections(
        $pdo, $id, ExaminationPanelCatalogRepository::selectionFromInput(['immunoserology'], $pdo)
    );
    $form = widalPanel(ExaminationPanelCatalogRepository::formForExamination($id));
    $expected = ['WIDAL_O' => 'Hasil manual 1:40', 'WIDAL_AO' => '(+) 1/160', 'WIDAL_BO' => '(+) 1/80', 'WIDAL_H' => '(+) 1/640'];
    $normalized = ExaminationParameterResultRepository::normalize($form, $expected);
    ExaminationParameterResultRepository::replace($pdo, $id, $normalized['rows']);
    $reloaded = widalPanel(ExaminationPanelCatalogRepository::formForExamination($id));
    widalAssert(array_column($reloaded[0]['parameters'], 'value', 'code') === $expected, 'Hasil berubah saat dibuka ulang.');
    $reloadedHtml = widalFormHtml($reloaded);
    widalAssert(substr_count($reloadedHtml, 'selected') === 3, 'Pilihan tersimpan harus terpilih saat dibuka ulang.');
    widalAssert(str_contains($reloadedHtml, 'value="Hasil manual 1:40"'), 'Hasil ketikan Widal O harus tampil saat dibuka ulang.');

    $mapLegacy = new ReflectionMethod(ExaminationRepository::class, 'legacyInputFromPanelSubmission');
    widalAssert($mapLegacy->invoke(null, $reloaded, $expected) === array_combine(array_values(WidalResultOptions::FIELDS), array_values($expected)), 'Nilai tabel kompatibilitas harus identik.');

    // Exercise the same template used by the A4 preview, without displaying patient data.
    $examination = ExaminationRepository::find($id);
    $panelForm = $reloaded;
    $flags = [];
    ob_start();
    require ROOT_PATH . '/app/views/examinations/panel_print.php';
    $printHtml = (string) ob_get_clean();
    foreach ($expected as $value) {
        widalAssert(str_contains($printHtml, View::e($value)), 'Preview cetak harus mempertahankan format hasil Widal.');
    }

    // Historical free text is retained for this examination only, even if stored in the legacy table.
    $pdo->prepare('DELETE FROM examination_parameter_results WHERE examination_id = ?')->execute([$id]);
    $pdo->prepare('INSERT INTO widal_results (examination_id, widal_ao, created_at, updated_at) VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')
        ->execute([$id, 'Positif 1:160']);
    $historical = widalPanel(ExaminationPanelCatalogRepository::formForExamination($id));
    $legacyResult = ExaminationParameterResultRepository::normalize($historical, ['WIDAL_AO' => 'Positif 1:160']);
    widalAssert($legacyResult['values']['WIDAL_AO'] === 'Positif 1:160', 'Hasil lama tidak boleh diubah otomatis.');
    widalAssert(str_contains(widalFormHtml($historical), 'value="Positif 1:160" selected'), 'Hasil lama harus tetap terpilih.');
    ExaminationParameterResultRepository::replace($pdo, $id, $legacyResult['rows']);
    $again = widalPanel(ExaminationPanelCatalogRepository::formForExamination($id));
    widalAssert(ExaminationParameterResultRepository::normalize($again, ['WIDAL_AO' => 'Positif 1:160'])['values']['WIDAL_AO'] === 'Positif 1:160', 'Hasil lama dinamis harus tetap dapat disimpan.');
    try {
        ExaminationParameterResultRepository::normalize($again, ['WIDAL_BO' => 'Positif 1:160']);
        throw new RuntimeException('Hasil lama tidak boleh menjadi pilihan baru pada parameter lain.');
    } catch (ValidationException) {
        // Expected.
    }
    widalAssert(ExaminationParameterResultRepository::normalize($again, ['WIDAL_AO' => '(+) 1/320'])['values']['WIDAL_AO'] === '(+) 1/320', 'Hasil lama harus dapat diganti dengan pilihan standar.');
    widalAssert(ExaminationParameterResultRepository::normalize($again, ['WIDAL_AO' => ''])['values']['WIDAL_AO'] === null, 'Hasil lama harus dapat dikosongkan.');
} finally {
    $pdo->rollBack();
}

echo "Widal dropdown regression: OK; transaksi uji dibatalkan.\n";
