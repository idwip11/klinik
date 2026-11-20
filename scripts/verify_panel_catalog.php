<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$expectedCounts = [
    'hematology_complete' => 16,
    'urinalysis_complete' => 18,
    'clinical_chemistry' => 13,
    'widal' => 4,
    'additional_rare' => 5,
    'immunoserology' => 11,
    'anc_ibu_hamil' => 7,
];

$panels = ExaminationPanelCatalogRepository::all();
$failures = [];

foreach ($panels as $panel) {
    $code = (string) $panel['code'];
    $actualCount = count($panel['parameters']);
    if (!array_key_exists($code, $expectedCounts)) {
        $failures[] = "Panel tidak dikenal: {$code}";
        continue;
    }
    if ($actualCount !== $expectedCounts[$code]) {
        $failures[] = "Jumlah parameter {$code}: diharapkan {$expectedCounts[$code]}, aktual {$actualCount}";
    }
    unset($expectedCounts[$code]);
}

$selection = ExaminationPanelCatalogRepository::selectionFromInput(['hematology_complete']);
$selectionCodes = array_column($selection, 'code');
if ($selectionCodes !== ['hematology_complete', 'widal']) {
    $failures[] = 'Pemilihan panel harus menambahkan Widal otomatis tanpa Imunoserologi.';
}
if (($selection[1]['is_auto_included'] ?? false) !== true) {
    $failures[] = 'Panel Widal harus ditandai sebagai panel otomatis.';
}
$withImmunoserology = ExaminationPanelCatalogRepository::selectionFromInput(['hematology_complete', 'immunoserology']);
if (array_column($withImmunoserology, 'code') !== ['hematology_complete', 'widal', 'immunoserology']
    || $withImmunoserology[2]['is_auto_included']) {
    $failures[] = 'Imunoserologi hanya disertakan saat dipilih secara manual.';
}

foreach (array_keys($expectedCounts) as $missingCode) {
    $failures[] = "Panel tidak ditemukan: {$missingCode}";
}

$alwaysIncluded = array_values(array_filter(
    $panels,
    static fn (array $panel): bool => $panel['is_always_included']
));
if (count($alwaysIncluded) !== 1 || $alwaysIncluded[0]['code'] !== 'widal') {
    $failures[] = 'Hanya panel Widal yang boleh selalu disertakan.';
}

foreach ($panels as $panel) {
    foreach ($panel['parameters'] as $parameter) {
        if ($parameter['is_required']) {
            $failures[] = "Parameter {$parameter['code']} seharusnya opsional pada Fase 2.";
        }
        if (!$parameter['print_when_empty']) {
            $failures[] = "Parameter {$parameter['code']} harus tercetak saat kosong.";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Verifikasi katalog panel gagal:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

$parameterCount = array_sum(array_map(
    static fn (array $panel): int => count($panel['parameters']),
    $panels
));
echo sprintf("Katalog panel valid: %d panel aktif, %d parameter aktif, dan pemilihan beberapa panel valid.\n", count($panels), $parameterCount);
