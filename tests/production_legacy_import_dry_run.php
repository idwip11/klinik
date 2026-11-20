<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string) $error . PHP_EOL);
    exit(1);
});

$summary = (new LegacyImportService())->run(
    __DIR__ . '/fixtures/legacy_mcu_synthetic.csv',
    'mcu_general', '__PRODUCTION_COMPATIBILITY', true
);
if ($summary['valid_rows'] !== 1 || $summary['imported_rows'] !== 0 || $summary['failed_rows'] !== 0) {
    throw new RuntimeException('Legacy import dry-run did not accept the synthetic fixture.');
}
echo "Production legacy import compatibility: PASS; dry-run only, no imported rows.\n";
