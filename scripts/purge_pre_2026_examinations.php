<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

const RETENTION_START = ExaminationRepository::DATA_RETENTION_START;
const REQUIRED_CONFIRMATION = 'HAPUS_DATA_SEBELUM_2026';

$options = getopt('', ['dry-run', 'execute', 'confirm:']);
$dryRun = array_key_exists('dry-run', $options);
$execute = array_key_exists('execute', $options);

if ($dryRun === $execute) {
    fwrite(STDERR, "Gunakan tepat satu mode: --dry-run atau --execute --confirm=" . REQUIRED_CONFIRMATION . "\n");
    exit(1);
}
if ($execute && (($options['confirm'] ?? null) !== REQUIRED_CONFIRMATION)) {
    fwrite(STDERR, "Konfirmasi tidak sesuai. Tidak ada data yang dihapus.\n");
    exit(1);
}

$pdo = Database::connection();
$count = static function (string $table) use ($pdo): int {
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM ' . $table . ' child
         INNER JOIN examinations e ON e.id = child.examination_id
         WHERE e.examined_on < :retention_start'
    );
    $statement->execute(['retention_start' => RETENTION_START]);
    return (int) $statement->fetchColumn();
};

$examinationStatement = $pdo->prepare('SELECT COUNT(*) FROM examinations WHERE examined_on < :retention_start');
$examinationStatement->execute(['retention_start' => RETENTION_START]);
$examinationCount = (int) $examinationStatement->fetchColumn();

$legacyPatientStatement = $pdo->prepare(
    'SELECT COUNT(DISTINCT e.patient_id)
     FROM examinations e
     WHERE e.examined_on < :retention_start
       AND NOT EXISTS (
           SELECT 1
           FROM examinations retained
           WHERE retained.patient_id = e.patient_id
             AND retained.examined_on >= :retention_start
       )'
);
$legacyPatientStatement->execute(['retention_start' => RETENTION_START]);
$legacyPatientCount = (int) $legacyPatientStatement->fetchColumn();

$dependentTables = [
    'vital_results',
    'blood_results',
    'urine_results',
    'drug_results',
    'widal_results',
    'examination_panel_selections',
    'examination_parameter_results',
    'examination_result_flags',
    'print_logs',
];

echo "Batas retensi: " . RETENTION_START . "\n";
echo "Pemeriksaan yang akan dihapus: {$examinationCount}\n";
echo "Pasien yang hanya memiliki pemeriksaan sebelum 2026: {$legacyPatientCount}\n";
foreach ($dependentTables as $table) {
    echo "- {$table}: " . $count($table) . " baris terkait\n";
}
echo "Pasien yang juga memiliki pemeriksaan mulai 2026, pengguna, nilai rujukan, dan riwayat audit tidak dihapus.\n";

if ($dryRun) {
    echo "Mode simulasi selesai. Tidak ada data yang dihapus.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $legacyPatientIdsStatement = $pdo->prepare(
        'SELECT DISTINCT e.patient_id
         FROM examinations e
         WHERE e.examined_on < :retention_start
           AND NOT EXISTS (
               SELECT 1
               FROM examinations retained
               WHERE retained.patient_id = e.patient_id
                 AND retained.examined_on >= :retention_start
           )
         FOR UPDATE'
    );
    $legacyPatientIdsStatement->execute(['retention_start' => RETENTION_START]);
    $legacyPatientIds = array_map('intval', $legacyPatientIdsStatement->fetchAll(PDO::FETCH_COLUMN));

    foreach ($dependentTables as $table) {
        $statement = $pdo->prepare(
            'DELETE child
             FROM ' . $table . ' child
             INNER JOIN examinations e ON e.id = child.examination_id
             WHERE e.examined_on < :retention_start'
        );
        $statement->execute(['retention_start' => RETENTION_START]);
    }

    $statement = $pdo->prepare('DELETE FROM examinations WHERE examined_on < :retention_start');
    $statement->execute(['retention_start' => RETENTION_START]);

    if ($legacyPatientIds !== []) {
        $placeholders = implode(', ', array_fill(0, count($legacyPatientIds), '?'));
        $statement = $pdo->prepare(
            'DELETE FROM patients
             WHERE id IN (' . $placeholders . ')
               AND NOT EXISTS (
                   SELECT 1 FROM examinations WHERE examinations.patient_id = patients.id
               )'
        );
        $statement->execute($legacyPatientIds);
    }

    $statement = $pdo->prepare('DELETE FROM lab_number_sequences WHERE examined_on < :retention_start');
    $statement->execute(['retention_start' => RETENTION_START]);

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

echo "Penghapusan pemeriksaan sebelum " . RETENTION_START . " selesai.\n";
