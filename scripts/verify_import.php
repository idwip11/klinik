<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$options = getopt('', ['batch-id:', 'file::']);
$batchId = isset($options['batch-id']) ? (int) $options['batch-id'] : 0;
$sourceFile = isset($options['file']) ? (string) $options['file'] : null;

if ($batchId < 1) {
    fwrite(STDERR, "Pemakaian: php scripts/verify_import.php --batch-id=1 [--file=/path/sumber.csv]\n");
    exit(1);
}

$pdo = Database::connection();
$statement = $pdo->prepare('SELECT * FROM import_batches WHERE id = :id LIMIT 1');
$statement->execute(['id' => $batchId]);
$batch = $statement->fetch();
if (!is_array($batch)) {
    fwrite(STDERR, "Batch impor tidak ditemukan.\n");
    exit(1);
}

$checks = [];
$addCheck = static function (string $label, bool $passed, string $detail) use (&$checks): void {
    $checks[] = compact('label', 'passed', 'detail');
};

$accounted = (int) $batch['imported_rows'] + (int) $batch['skipped_rows'] + (int) $batch['failed_rows'];
$addCheck('Rekonsiliasi baris', $accounted === (int) $batch['total_rows'], "tercatat={$accounted}; total={$batch['total_rows']}");

$statement = $pdo->prepare('SELECT COUNT(*) FROM examinations WHERE import_batch_id = :id');
$statement->execute(['id' => $batchId]);
$examCount = (int) $statement->fetchColumn();
$addCheck('Jumlah pemeriksaan', $examCount === (int) $batch['imported_rows'], "database={$examCount}; batch={$batch['imported_rows']}");

$statement = $pdo->prepare("SELECT COUNT(*) FROM examinations WHERE import_batch_id = :id AND status <> 'draft'");
$statement->execute(['id' => $batchId]);
$nonDraft = (int) $statement->fetchColumn();
$addCheck('Status aman hasil impor', $nonDraft === 0, "bukan_draft={$nonDraft}");

$resultTable = $batch['examination_type'] === 'drug_screen' ? 'drug_results' : 'blood_results';
$statement = $pdo->prepare(
    "SELECT COUNT(*) FROM examinations e
     LEFT JOIN {$resultTable} r ON r.examination_id = e.id
     WHERE e.import_batch_id = :id AND r.id IS NULL"
);
$statement->execute(['id' => $batchId]);
$missingResults = (int) $statement->fetchColumn();
$addCheck('Kelengkapan tabel hasil utama', $missingResults === 0, "tanpa_hasil={$missingResults}");

$statement = $pdo->prepare(
    'SELECT COUNT(*) FROM (
        SELECT source_row_number FROM examinations WHERE import_batch_id = :id
        GROUP BY source_row_number HAVING COUNT(*) > 1
     ) duplicate_rows'
);
$statement->execute(['id' => $batchId]);
$duplicateSourceRows = (int) $statement->fetchColumn();
$addCheck('Keunikan baris sumber', $duplicateSourceRows === 0, "duplikat={$duplicateSourceRows}");

$statement = $pdo->prepare("SELECT COUNT(*) FROM import_errors WHERE import_batch_id = :id AND severity = 'error'");
$statement->execute(['id' => $batchId]);
$errorCount = (int) $statement->fetchColumn();
$addCheck('Catatan kegagalan tersedia', (int) $batch['failed_rows'] === 0 || $errorCount > 0, "error_tercatat={$errorCount}");

if ($sourceFile !== null && $sourceFile !== '') {
    $currentHash = is_file($sourceFile) ? hash_file('sha256', $sourceFile) : false;
    $addCheck('Integritas berkas sumber', is_string($currentHash) && hash_equals((string) $batch['source_sha256'], $currentHash), 'SHA-256 dibandingkan');
}

echo "Verifikasi batch #{$batchId}\n";
echo "Status batch: {$batch['status']}\n\n";
foreach ($checks as $check) {
    echo sprintf("[%s] %s — %s\n", $check['passed'] ? 'PASS' : 'FAIL', $check['label'], $check['detail']);
}

$failed = array_filter($checks, static fn (array $check): bool => !$check['passed']);
echo "\nHasil: " . ($failed === [] ? 'semua pemeriksaan otomatis lulus' : count($failed) . ' pemeriksaan gagal') . "\n";
echo "Catatan: tetap lakukan sampling manual identitas, hasil, dan cetakan sebelum persetujuan migrasi.\n";
exit($failed === [] ? 0 : 2);
