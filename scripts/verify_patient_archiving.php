<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::connection();
$userId = (int) $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
if ($userId < 1) {
    throw new RuntimeException('Tidak ada pengguna untuk menjalankan verifikasi.');
}

$suffix = strtoupper(bin2hex(random_bytes(6)));
$medicalRecordNumber = 'VERIFY-ARCHIVE-' . $suffix;
$protectedMedicalRecordNumber = 'VERIFY-PROTECT-' . $suffix;
$labNumber = 'VERIFY-LAB-' . $suffix;

$pdo->beginTransaction();

try {
    $insertPatient = $pdo->prepare(
        'INSERT INTO patients (medical_record_number, full_name, created_at, updated_at)
         VALUES (:medical_record_number, :full_name, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );

    $insertPatient->execute([
        'medical_record_number' => $medicalRecordNumber,
        'full_name' => 'VERIFIKASI ARSIP PASIEN',
    ]);
    $patientId = (int) $pdo->lastInsertId();

    PatientRepository::archive($patientId, $medicalRecordNumber, $userId);
    if (PatientRepository::search($medicalRecordNumber) !== []) {
        throw new RuntimeException('Pasien arsip masih tampil pada pencarian aktif.');
    }
    $archivedPatients = PatientRepository::archived($medicalRecordNumber);
    if (count($archivedPatients) !== 1 || (int) $archivedPatients[0]['id'] !== $patientId) {
        throw new RuntimeException('Pasien arsip tidak ditemukan pada katalog arsip.');
    }
    try {
        ExaminationRepository::create([
            'patient_id' => $patientId,
            'lab_number' => 'VERIFY-ARCHIVED-' . $suffix,
            'examined_on' => date('Y-m-d'),
            'examination_type' => 'drug_screen',
        ], $userId);
        throw new RuntimeException('Pasien arsip seharusnya tidak dapat dibuatkan pemeriksaan baru.');
    } catch (ValidationException $exception) {
        if (!str_contains(firstError($exception), 'diarsipkan')) {
            throw $exception;
        }
    }

    PatientRepository::restore($patientId, $userId);
    $restored = PatientRepository::find($patientId);
    if ($restored === false || $restored['archived_at'] !== null) {
        throw new RuntimeException('Pasien tidak berhasil dipulihkan.');
    }

    PatientRepository::deletePermanently($patientId, $medicalRecordNumber, $userId);
    if (PatientRepository::find($patientId) !== false) {
        throw new RuntimeException('Pasien tanpa pemeriksaan tidak berhasil dihapus permanen.');
    }

    $insertPatient->execute([
        'medical_record_number' => $protectedMedicalRecordNumber,
        'full_name' => 'VERIFIKASI RIWAYAT PASIEN',
    ]);
    $protectedPatientId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO examinations
            (patient_id, lab_number, examination_type, status, examined_on, created_by, updated_by, created_at, updated_at)
         VALUES
            (:patient_id, :lab_number, :examination_type, :status, CURDATE(), :created_by, :updated_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    )->execute([
        'patient_id' => $protectedPatientId,
        'lab_number' => $labNumber,
        'examination_type' => 'mcu_general',
        'status' => 'draft',
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);

    try {
        PatientRepository::deletePermanently($protectedPatientId, $protectedMedicalRecordNumber, $userId);
        throw new RuntimeException('Pasien dengan riwayat pemeriksaan seharusnya tidak dapat dihapus permanen.');
    } catch (ValidationException $exception) {
        if (!str_contains(firstError($exception), 'riwayat pemeriksaan')) {
            throw $exception;
        }
    }

    $pdo->rollBack();
    echo "Verifikasi pengarsipan dan penghapusan pasien berhasil.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

function firstError(ValidationException $exception): string
{
    $errors = $exception->errors();

    return (string) reset($errors);
}
