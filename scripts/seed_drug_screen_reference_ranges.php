<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::connection();
$userId = $pdo->query('SELECT id FROM users WHERE role = "admin" ORDER BY id LIMIT 1')->fetchColumn();
if ($userId === false) {
    throw new RuntimeException('Akun admin diperlukan untuk mencatat persetujuan konfigurasi.');
}

$rules = [
    'DRUG_AMPHETAMINE',
    'DRUG_MARIJUANA',
    'DRUG_MORPHINE',
    'DRUG_BENZODIAZEPINE',
    'DRUG_METHAMPHETAMINE',
    'DRUG_COCAINE',
];

$activeRule = $pdo->prepare(
    'SELECT id
     FROM reference_ranges
     WHERE test_code = :test_code
       AND sex = "ALL"
       AND min_age_days IS NULL AND max_age_days IS NULL
       AND min_age_years IS NULL AND max_age_years IS NULL
       AND approval_status = "approved" AND is_active = 1
     LIMIT 1'
);

$created = 0;
$skipped = 0;
foreach ($rules as $testCode) {
    $activeRule->execute(['test_code' => $testCode]);
    if ($activeRule->fetchColumn() !== false) {
        $skipped++;
        continue;
    }

    ReferenceRangeRepository::create([
        'test_code' => $testCode,
        'population' => 'ALL',
        'normal_values' => 'Negatif',
        'reference_note' => 'Konfigurasi lokal: nilai normal skrining narkoba Negatif atas permintaan klinik (31-08-2026).',
    ], (int) $userId);
    $created++;
}

echo "Aturan aktif dibuat: {$created}; aturan aktif yang sudah ada: {$skipped}." . PHP_EOL;
