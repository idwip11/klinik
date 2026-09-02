<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::connection();
$failures = [];

$panels = array_values(array_filter(
    ExaminationPanelCatalogRepository::all(),
    static fn (array $panel): bool => in_array($panel['code'], ['hematology_complete', 'immunoserology'], true)
));
$normalized = ExaminationParameterResultRepository::normalize($panels, [
    'HGB' => '12,50',
    'BLOOD_GROUP' => 'AB',
    'HBSAG_RAPID' => 'Non Reaktif',
]);
if (($normalized['values']['HGB'] ?? null) !== '12.5') {
    $failures[] = 'Angka dengan koma tidak dinormalisasi menjadi desimal database.';
}
$grouped = ExaminationParameterResultRepository::normalize($panels, ['HGB' => '5.700']);
if (($grouped['values']['HGB'] ?? null) !== '5700') {
    $failures[] = 'Angka ribuan dengan titik tidak dinormalisasi dengan benar.';
}
try {
    ExaminationParameterResultRepository::normalize($panels, ['HGB' => '1.5']);
    $failures[] = 'Desimal dengan titik masih diterima, padahal format harus memakai koma.';
} catch (ValidationException) {
    // Expected.
}
if (($normalized['values']['BLOOD_GROUP'] ?? null) !== 'AB') {
    $failures[] = 'Pilihan Golongan Darah yang valid tidak diterima.';
}
if (count($normalized['rows']) !== 3) {
    $failures[] = 'Jumlah hasil terisi yang dinormalisasi tidak sesuai.';
}

try {
    ExaminationParameterResultRepository::normalize($panels, ['WIDAL_O' => 'Negatif']);
    $failures[] = 'Parameter dari panel yang tidak dipilih masih diterima.';
} catch (ValidationException) {
    // Expected.
}

try {
    ExaminationParameterResultRepository::normalize($panels, ['BLOOD_GROUP' => 'X']);
    $failures[] = 'Pilihan Golongan Darah yang tidak valid masih diterima.';
} catch (ValidationException) {
    // Expected.
}

$patientId = (int) $pdo->query('SELECT id FROM patients ORDER BY id LIMIT 1')->fetchColumn();
$userId = (int) $pdo->query('SELECT id FROM users WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
if ($patientId < 1 || $userId < 1) {
    $failures[] = 'Data pasien/pengguna lokal tidak tersedia untuk uji transaksi.';
} else {
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'INSERT INTO examinations
                (patient_id, lab_number, examination_type, status, examined_on, created_by, updated_by, created_at, updated_at)
             VALUES
                (:patient_id, :lab_number, "mcu_general", "draft", CURRENT_DATE(), :created_by, :updated_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute([
            'patient_id' => $patientId,
            'lab_number' => '__VERIFY_F4_' . bin2hex(random_bytes(6)),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        $examinationId = (int) $pdo->lastInsertId();
        $selections = ExaminationPanelCatalogRepository::selectionFromInput(['hematology_complete', 'urinalysis_complete'], $pdo);
        ExaminationPanelCatalogRepository::storeSelections($pdo, $examinationId, $selections);
        $form = ExaminationPanelCatalogRepository::formForExamination($examinationId);
        if (array_column($form, 'code') !== ['hematology_complete', 'urinalysis_complete', 'immunoserology']) {
            $failures[] = 'Form dinamis tidak mengikuti panel terpilih dan Imunoserologi otomatis.';
        }
        $stored = ExaminationParameterResultRepository::normalize($form, [
            'HGB' => '13,2',
            'URINE_COLOR' => 'Kuning Muda',
            'BLOOD_GROUP' => 'O',
        ]);
        ExaminationParameterResultRepository::replace($pdo, $examinationId, $stored['rows']);
        $countStatement = $pdo->prepare('SELECT COUNT(*) FROM examination_parameter_results WHERE examination_id = :id');
        $countStatement->execute(['id' => $examinationId]);
        if ((int) $countStatement->fetchColumn() !== 3) {
            $failures[] = 'Penyimpanan hasil parameter dinamis tidak sesuai.';
        }
    } finally {
        $pdo->rollBack();
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Verifikasi hasil panel gagal:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Form dan penyimpanan hasil panel valid; transaksi uji telah dibatalkan tanpa menyisakan data.\n";
