<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string) $error . PHP_EOL);
    exit(1);
});

function identityAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

foreach ([
    ['1996-06-06', '2026-10-20', '30 tahun 4 bulan 14 hari'],
    ['1997-01-04', '2026-10-20', '29 tahun 9 bulan 16 hari'],
    ['2026-10-18', '2026-10-20', '0 tahun 0 bulan 2 hari'],
    ['2026-10-20', '2026-10-20', '0 tahun 0 bulan 0 hari'],
    ['2000-02-29', '2024-02-29', '24 tahun 0 bulan 0 hari'],
    ['2000-01-31', '2000-02-01', '0 tahun 0 bulan 1 hari'],
    ['2026-10-21', '2026-10-20', '—'],
    ['2026-02-30', '2026-10-20', '—'],
    [null, '2026-10-20', '—'],
    ['2000-01-01', null, '—'],
] as [$birth, $examinedOn, $expected]) {
    identityAssert(View::formatAge($birth, $examinedOn) === $expected, 'Perhitungan umur tidak sesuai: ' . ($birth ?? 'kosong'));
}

$pdo = Database::connection();
// Connection-scoped shadow tables let us exercise real create/update methods,
// including their commits, without inserting or deleting real patient records.
foreach (['patients', 'examinations', 'examination_panel_selections', 'audit_logs'] as $table) {
    $definition = (string) $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
    $lines = array_filter(explode("\n", $definition), static fn(string $line): bool => preg_match('/^\s*CONSTRAINT .* FOREIGN KEY/', $line) !== 1);
    $definition = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', implode("\n", $lines));
    $definition = preg_replace('/,(\s*\))/', '$1', $definition);
    $pdo->exec($definition);
}
$pdo->exec('ALTER TABLE patients AUTO_INCREMENT = 900000000');
$pdo->exec('ALTER TABLE examinations AUTO_INCREMENT = 900000000');
$userId = (int) $pdo->query('SELECT id FROM users WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
identityAssert($userId > 0, 'Diperlukan pengguna aktif untuk pengujian.');
$pdo->exec('INSERT INTO patients (medical_record_number, full_name, sex, birth_date, created_at, updated_at) VALUES ("__IDENTITY_TEST", "Pasien sintetis identitas", "P", "1996-06-06", UTC_TIMESTAMP(), UTC_TIMESTAMP())');
$patientId = (int) $pdo->lastInsertId();
$baseInput = [
    'patient_id' => $patientId, 'lab_number' => '__IDENTITY_MCU', 'examination_type' => 'mcu_general',
    'examined_on' => '2026-10-20', 'examined_time' => '08:00', 'room' => 'Ruang uji',
    'panel_codes' => ['hematology_complete'], 'doctor_name' => '  dr. Contoh, Sp.PD  ',
];
$ids = [];
foreach (array_keys(ExaminationType::labels()) as $type) {
    $input = array_merge($baseInput, ['examination_type' => $type, 'lab_number' => '__IDENTITY_' . $type]);
    $id = ExaminationRepository::create($input, $userId);
    $ids[] = $id;
    $examination = ExaminationRepository::find($id);
    identityAssert($examination !== false && ($examination['doctor_name'] ?? null) === null, 'Input dokter lama masih disimpan untuk ' . $type);
    identityAssert(View::formatAge($examination['birth_date'], $examination['examined_on']) === '30 tahun 4 bulan 14 hari', 'Umur tidak sesuai tanggal periksa.');
    ExaminationRepository::updateDocumentDates($id, '2026-10-20', '2026-10-21', $userId);
    identityAssert(ExaminationRepository::find($id)['result_date'] === '2026-10-21', 'Tanggal hasil tidak bisa dikoreksi.');
    ExaminationRepository::updateDocumentDates($id, '2026-10-21', '2026-10-21', $userId);
    $examination = ExaminationRepository::find($id);
    identityAssert(View::formatAge($examination['birth_date'], $examination['examined_on']) === '30 tahun 4 bulan 15 hari', 'Umur tidak mengikuti koreksi tanggal.');
    $info = array_column(ExaminationExcelExporter::patientInfo($examination), 1, 0);
    identityAssert(!array_key_exists('Dokter', $info) && $info['Umur'] === '30 tahun 4 bulan 15 hari', 'Identitas Excel tidak sesuai atau masih memuat dokter.');
}
foreach ([['2026-02-30', '2026-10-21'], ['2026-10-20', '2026-02-30'], ['2026-10-21', '2026-10-20']] as [$examinedOn, $resultDate]) {
    try {
        ExaminationRepository::updateDocumentDates($ids[0], $examinedOn, $resultDate, $userId);
        throw new RuntimeException('Tanggal dokumen tidak valid diterima.');
    } catch (ValidationException) {
        // Expected.
    }
}
$optionalId = ExaminationRepository::create(array_merge($baseInput, ['lab_number' => '__IDENTITY_EMPTY', 'doctor_name' => ['input lama diabaikan']]), $userId);
identityAssert((ExaminationRepository::find($optionalId)['doctor_name'] ?? null) === null, 'Input dokter harus diabaikan.');
$history = PatientRepository::examinations($patientId);
identityAssert(count($history) === 4 && !array_key_exists('doctor_name', $history[0]), 'Riwayat pasien masih memuat nama dokter.');
identityAssert((int) $pdo->query('SELECT COUNT(*) FROM examinations')->fetchColumn() === 4, 'Validasi gagal membuat pemeriksaan tambahan.');
echo "Identity regression: OK; umur kalender, 3 jenis pemeriksaan tanpa dokter, koreksi tanggal, validasi, dan Excel. Hanya tabel temporer.\n";
