<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$userId = Database::connection()->query('SELECT id FROM users WHERE role = "admin" ORDER BY id LIMIT 1')->fetchColumn();
if ($userId === false) {
    throw new RuntimeException('Akun admin diperlukan untuk mencatat persetujuan konfigurasi.');
}

/**
 * Transkripsi dari tabel nilai rujukan yang dibagikan pada 27 Agustus 2026.
 * Batas anak-anak tidak tercantum pada sumber; 0–17 tahun digunakan sebagai
 * nilai awal dan dapat diperbarui dari menu Konfigurasi nilai rujukan.
 *
 * @var list<array{test_code: string, population: string, unit: string, lower_bound?: string, upper_bound?: string, upper_inclusive?: bool, max_age_days?: int}>
 */
$rules = [
    // Perempuan
    ['test_code' => 'HGB', 'population' => 'P', 'unit' => 'g/dL', 'lower_bound' => '11.4', 'upper_bound' => '15.1'],
    ['test_code' => 'HCT', 'population' => 'P', 'unit' => '%', 'lower_bound' => '38', 'upper_bound' => '42'],
    ['test_code' => 'RBC', 'population' => 'P', 'unit' => 'Juta/cmm', 'lower_bound' => '4.0', 'upper_bound' => '5.0'],
    ['test_code' => 'WBC', 'population' => 'P', 'unit' => 'sel/cmm', 'lower_bound' => '4700', 'upper_bound' => '11300'],
    ['test_code' => 'PLT', 'population' => 'P', 'unit' => 'sel/cmm', 'lower_bound' => '142000', 'upper_bound' => '424000'],
    ['test_code' => 'GDS', 'population' => 'P', 'unit' => 'mg/dL', 'upper_bound' => '200', 'upper_inclusive' => false],
    ['test_code' => 'CHOL', 'population' => 'P', 'unit' => 'mg/dL', 'upper_bound' => '200', 'upper_inclusive' => false],

    // Laki-laki
    ['test_code' => 'HGB', 'population' => 'L', 'unit' => 'g/dL', 'lower_bound' => '13.4', 'upper_bound' => '17.7'],
    ['test_code' => 'HCT', 'population' => 'L', 'unit' => '%', 'lower_bound' => '40', 'upper_bound' => '47'],
    ['test_code' => 'RBC', 'population' => 'L', 'unit' => 'Juta/cmm', 'lower_bound' => '4.0', 'upper_bound' => '5.5'],
    ['test_code' => 'WBC', 'population' => 'L', 'unit' => 'sel/cmm', 'lower_bound' => '4300', 'upper_bound' => '10300'],
    ['test_code' => 'PLT', 'population' => 'L', 'unit' => 'sel/cmm', 'lower_bound' => '142000', 'upper_bound' => '424000'],
    ['test_code' => 'GDS', 'population' => 'L', 'unit' => 'mg/dL', 'upper_bound' => '200', 'upper_inclusive' => false],
    ['test_code' => 'FBS', 'population' => 'L', 'unit' => 'mg/dL', 'lower_bound' => '60', 'upper_bound' => '100'],
    ['test_code' => 'GLUCOSE_2H_PP', 'population' => 'L', 'unit' => 'mg/dL', 'upper_bound' => '130', 'upper_inclusive' => false],
    ['test_code' => 'SGOT', 'population' => 'L', 'unit' => 'U/L', 'lower_bound' => '0', 'upper_bound' => '40'],
    ['test_code' => 'SGPT', 'population' => 'L', 'unit' => 'U/L', 'lower_bound' => '0', 'upper_bound' => '41'],
    ['test_code' => 'UREA', 'population' => 'L', 'unit' => 'mg/dL', 'lower_bound' => '19', 'upper_bound' => '49'],
    ['test_code' => 'CREATININE', 'population' => 'L', 'unit' => 'mg/dL', 'upper_bound' => '1.2', 'upper_inclusive' => false],
    ['test_code' => 'UA', 'population' => 'L', 'unit' => 'mg/dL', 'lower_bound' => '3.4', 'upper_bound' => '7.0'],
    ['test_code' => 'CHOL', 'population' => 'L', 'unit' => 'mg/dL', 'upper_bound' => '200', 'upper_inclusive' => false],

    // Anak-anak — batas usia sumber tidak tersedia; dipakai sementara 0–17 tahun.
    ['test_code' => 'HGB', 'population' => 'CHILD', 'unit' => 'g/dL', 'lower_bound' => '13.4', 'upper_bound' => '17.7', 'max_age_days' => 6574],
    ['test_code' => 'HCT', 'population' => 'CHILD', 'unit' => '%', 'lower_bound' => '40', 'upper_bound' => '47', 'max_age_days' => 6574],
    ['test_code' => 'RBC', 'population' => 'CHILD', 'unit' => 'Juta/cmm', 'lower_bound' => '4.0', 'upper_bound' => '5.5', 'max_age_days' => 6574],
    ['test_code' => 'WBC', 'population' => 'CHILD', 'unit' => 'sel/cmm', 'lower_bound' => '4300', 'upper_bound' => '10300', 'max_age_days' => 6574],
    ['test_code' => 'PLT', 'population' => 'CHILD', 'unit' => 'sel/cmm', 'lower_bound' => '142000', 'upper_bound' => '424000', 'max_age_days' => 6574],
    ['test_code' => 'GDS', 'population' => 'CHILD', 'unit' => 'mg/dL', 'upper_bound' => '200', 'upper_inclusive' => false, 'max_age_days' => 6574],

    // Nilai umum hematologi tambahan pada tabel DL/DLL.
    ['test_code' => 'LYMPH_PCT', 'population' => 'ALL', 'unit' => '%', 'lower_bound' => '20', 'upper_bound' => '40'],
    ['test_code' => 'MID_PCT', 'population' => 'ALL', 'unit' => '%', 'lower_bound' => '3', 'upper_bound' => '15'],
    ['test_code' => 'GRAN_PCT', 'population' => 'ALL', 'unit' => '%', 'lower_bound' => '50', 'upper_bound' => '70'],
    ['test_code' => 'MCV', 'population' => 'ALL', 'unit' => 'fL', 'lower_bound' => '80', 'upper_bound' => '93'],
    ['test_code' => 'MCH', 'population' => 'ALL', 'unit' => 'pg', 'lower_bound' => '27', 'upper_bound' => '31'],
    ['test_code' => 'MCHC', 'population' => 'ALL', 'unit' => 'g/dL', 'lower_bound' => '32', 'upper_bound' => '36'],
    ['test_code' => 'EOSINOPHIL', 'population' => 'ALL', 'unit' => '%', 'lower_bound' => '0', 'upper_bound' => '4'],
    ['test_code' => 'BASOPHIL', 'population' => 'ALL', 'unit' => '%', 'lower_bound' => '0', 'upper_bound' => '1'],
    ['test_code' => 'NEUTROPHIL', 'population' => 'ALL', 'unit' => '%', 'lower_bound' => '51', 'upper_bound' => '67'],
    ['test_code' => 'LYMPHOCYTE', 'population' => 'ALL', 'unit' => '%', 'lower_bound' => '25', 'upper_bound' => '33'],
    ['test_code' => 'MONOCYTE', 'population' => 'ALL', 'unit' => '%', 'lower_bound' => '2', 'upper_bound' => '5'],
];

$connection = Database::connection();
$exists = $connection->prepare(
    'SELECT id FROM reference_ranges
     WHERE test_code = :test_code AND sex = :sex
       AND min_age_days <=> :min_age_days AND max_age_days <=> :max_age_days
       AND lower_bound <=> :lower_bound AND upper_bound <=> :upper_bound
       AND lower_inclusive = :lower_inclusive AND upper_inclusive = :upper_inclusive
       AND unit = :unit AND is_active = 1
     LIMIT 1'
);
$created = 0;
$skipped = 0;
foreach ($rules as $rule) {
    $sex = $rule['population'] === 'P' ? 'P' : ($rule['population'] === 'L' ? 'L' : 'ALL');
    $minAge = $rule['population'] === 'CHILD' ? 0 : null;
    $maxAge = $rule['max_age_days'] ?? null;
    $lower = $rule['lower_bound'] ?? null;
    $upper = $rule['upper_bound'] ?? null;
    $upperInclusive = $rule['upper_inclusive'] ?? true;
    $exists->execute([
        'test_code' => $rule['test_code'],
        'sex' => $sex,
        'min_age_days' => $minAge,
        'max_age_days' => $maxAge,
        'lower_bound' => $lower,
        'upper_bound' => $upper,
        'lower_inclusive' => 1,
        'upper_inclusive' => $upperInclusive ? 1 : 0,
        'unit' => $rule['unit'],
    ]);
    if ($exists->fetchColumn() !== false) {
        $skipped++;
        continue;
    }

    $input = [
        'test_code' => $rule['test_code'],
        'population' => $rule['population'],
        'unit' => $rule['unit'],
        'lower_bound' => $lower,
        'upper_bound' => $upper,
        'lower_inclusive' => '1',
        'reference_note' => 'Transkripsi tabel nilai rujukan 27-08-2026',
    ];
    if ($upperInclusive) {
        $input['upper_inclusive'] = '1';
    }
    if ($rule['population'] === 'CHILD') {
        $input['max_age_days'] = (string) $maxAge;
    }
    ReferenceRangeRepository::createDraft($input, (int) $userId);
    $created++;
}

echo "Draft dibuat: {$created}; aturan aktif yang sudah ada: {$skipped}. Draft tetap memerlukan persetujuan tenaga klinis melalui aplikasi." . PHP_EOL;
