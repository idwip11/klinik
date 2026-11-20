<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::connection();
$userId = (int) $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
if ($userId < 1) {
    throw new RuntimeException('Tidak ada pengguna untuk menjalankan verifikasi.');
}

$suffix = strtoupper(bin2hex(random_bytes(6)));
$medicalRecordNumber = 'VERIFY-DELETE-' . $suffix;
$labNumber = 'VERIFY-LAB-' . $suffix;

$pdo->beginTransaction();

try {
    $insertPatient = $pdo->prepare(
        'INSERT INTO patients (medical_record_number, full_name, created_at, updated_at)
         VALUES (:medical_record_number, :full_name, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );

    $insertPatient->execute(['medical_record_number' => $medicalRecordNumber, 'full_name' => 'VERIFIKASI HAPUS PERMANEN']);
    $patientId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO examinations
            (patient_id, lab_number, examination_type, status, examined_on, created_by, updated_by, created_at, updated_at)
         VALUES
            (:patient_id, :lab_number, :examination_type, :status, CURDATE(), :created_by, :updated_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    )->execute([
        'patient_id' => $patientId,
        'lab_number' => $labNumber,
        'examination_type' => 'drug_screen',
        'status' => 'draft',
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
    $examinationId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO print_logs (examination_id, printed_by, document_type, printed_at)
         VALUES
            (:examination_id, :printed_by, :document_type, UTC_TIMESTAMP())'
    )->execute([
        'examination_id' => $examinationId,
        'printed_by' => $userId,
        'document_type' => 'drug_screen',
    ]);

    PatientRepository::deletePermanently($patientId, $medicalRecordNumber, $userId);
    if (PatientRepository::find($patientId) !== false) {
        throw new RuntimeException('Pasien dengan riwayat tidak berhasil dihapus permanen.');
    }
    $remainingExaminations = $pdo->prepare('SELECT COUNT(*) FROM examinations WHERE id = :id');
    $remainingExaminations->execute(['id' => $examinationId]);
    if ((int) $remainingExaminations->fetchColumn() !== 0) {
        throw new RuntimeException('Pemeriksaan terkait masih tersisa setelah penghapusan pasien.');
    }
    $remainingPrintLogs = $pdo->prepare('SELECT COUNT(*) FROM print_logs WHERE examination_id = :id');
    $remainingPrintLogs->execute(['id' => $examinationId]);
    if ((int) $remainingPrintLogs->fetchColumn() !== 0) {
        throw new RuntimeException('Log cetak terkait masih tersisa setelah penghapusan pasien.');
    }

    $pdo->rollBack();
    echo "Verifikasi penghapusan permanen pasien berhasil.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
