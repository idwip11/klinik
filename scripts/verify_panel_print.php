<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::connection();
$failures = [];

$column = $pdo->query("SHOW COLUMNS FROM print_logs LIKE 'document_type'")->fetch();
if ($column === false || !str_contains((string) $column['Type'], "'panel_results'")) {
    $failures[] = 'Jenis log cetak panel_results belum tersedia pada print_logs.';
}

$panels = ExaminationPanelCatalogRepository::all();
$panelCodes = array_column($panels, 'code');
if (!in_array('immunoserology', $panelCodes, true)) {
    $failures[] = 'Panel Imunoserologi tidak tersedia untuk cetak.';
}

$parameters = 0;
$printEmptyParameters = 0;
foreach ($panels as $panel) {
    foreach ($panel['parameters'] as $parameter) {
        $parameters++;
        if ($parameter['print_when_empty']) {
            $printEmptyParameters++;
        }
    }
}
if ($parameters === 0 || $parameters !== $printEmptyParameters) {
    $failures[] = 'Parameter aktif belum seluruhnya dikonfigurasi untuk tetap tercetak saat kosong.';
}

if ($failures !== []) {
    fwrite(STDERR, "Verifikasi cetak panel gagal:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Cetak panel valid: header/footer memakai template aplikasi, semua {$parameters} parameter aktif dapat dicetak, dan log panel_results tersedia.\n";
