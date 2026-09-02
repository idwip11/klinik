<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::connection();
$testMedicalRecordNumber = str_repeat('9', 49);
$expectedSuggestion = '1' . str_repeat('0', 49);

$pdo->beginTransaction();

try {
    $statement = $pdo->prepare(
        'INSERT INTO patients (medical_record_number, full_name, created_at, updated_at)
         VALUES (:medical_record_number, :full_name, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $statement->execute([
        'medical_record_number' => $testMedicalRecordNumber,
        'full_name' => 'VERIFIKASI NOMOR RM OTOMATIS',
    ]);

    $suggestion = PatientRepository::nextMedicalRecordNumber($pdo);
    if ($suggestion !== $expectedSuggestion) {
        throw new RuntimeException(sprintf('Saran tidak sesuai. Diharapkan %s, mendapat %s.', $expectedSuggestion, $suggestion));
    }

    $pdo->rollBack();
    echo "Verifikasi nomor RM otomatis berhasil.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
