<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$options = getopt('', ['file:', 'type:', 'source-sheet:', 'user-id::', 'dry-run']);
$file = isset($options['file']) ? (string) $options['file'] : '';
$typeInput = isset($options['type']) ? (string) $options['type'] : '';
$sourceSheet = isset($options['source-sheet']) ? (string) $options['source-sheet'] : '';
$dryRun = array_key_exists('dry-run', $options);
$userId = isset($options['user-id']) ? (int) $options['user-id'] : null;

$type = match ($typeInput) {
    'mcu', 'mcu_general' => 'mcu_general',
    'drug', 'drug_screen' => 'drug_screen',
    default => $typeInput,
};

if ($file === '' || $type === '' || $sourceSheet === '') {
    fwrite(STDERR, "Pemakaian:\n");
    fwrite(STDERR, "  php scripts/import_legacy_csv.php --file=/path/data.csv --type=mcu --source-sheet=\"LOGBOOK MCU\" --dry-run\n");
    fwrite(STDERR, "  php scripts/import_legacy_csv.php --file=/path/data.csv --type=mcu --source-sheet=\"LOGBOOK MCU\" --user-id=1\n");
    exit(1);
}

try {
    $pdo = $dryRun ? null : Database::connection();
    $summary = (new LegacyImportService($pdo))->run($file, $type, $sourceSheet, $dryRun, $userId);

    echo "Mode              : {$summary['mode']}\n";
    echo "Berkas            : {$summary['source_filename']}\n";
    echo "Sheet             : {$summary['source_sheet']}\n";
    echo "SHA-256           : {$summary['source_sha256']}\n";
    if ($summary['batch_id'] !== null) {
        echo "Batch ID          : {$summary['batch_id']}\n";
    }
    echo "Total baris       : {$summary['total_rows']}\n";
    echo "Baris valid       : {$summary['valid_rows']}\n";
    echo "Berhasil diimpor  : {$summary['imported_rows']}\n";
    echo "Dilewati          : {$summary['skipped_rows']}\n";
    echo "Gagal             : {$summary['failed_rows']}\n";
    echo "Peringatan        : {$summary['warning_count']}\n";

    if ($summary['issue_samples'] !== []) {
        echo "\nContoh masalah (tanpa nilai pasien):\n";
        foreach ($summary['issue_samples'] as $issue) {
            echo sprintf(
                "- baris %d [%s] %s/%s: %s\n",
                $issue['row'],
                $issue['severity'],
                $issue['field'],
                $issue['code'],
                $issue['message']
            );
        }
    }

    exit($summary['failed_rows'] > 0 ? 2 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Impor gagal dimulai: ' . $exception->getMessage() . "\n");
    exit(1);
}
