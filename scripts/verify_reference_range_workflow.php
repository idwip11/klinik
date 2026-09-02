<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::connection();
$failures = [];
$ageYearsMethod = new ReflectionMethod(ReferenceEvaluator::class, 'ageYears');
foreach ([
    ['2008-08-30', '2026-08-29', 17],
    ['2008-08-30', '2026-08-30', 18],
    ['2008-02-29', '2026-02-28', 17],
    ['2008-02-29', '2026-03-01', 18],
] as [$birthDate, $examinedOn, $expectedYears]) {
    if ($ageYearsMethod->invoke(null, $birthDate, $examinedOn) !== $expectedYears) {
        $failures[] = 'Usia ulang tahun penuh tidak sesuai pada batas ulang tahun atau tahun kabisat.';
        break;
    }
}
$catalog = ReferenceRangeRepository::catalog();
$panelParameterCount = 0;
foreach (ExaminationPanelCatalogRepository::all() as $panel) {
    $panelParameterCount += count($panel['parameters']);
}
if ($panelParameterCount !== 67 || count($catalog) < $panelParameterCount) {
    $failures[] = 'Katalog nilai rujukan belum mencakup seluruh 67 parameter panel.';
}

$columns = $pdo->query('SHOW COLUMNS FROM reference_ranges')->fetchAll();
$columnNames = array_column($columns, 'Field');
foreach (['approval_status', 'created_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note', 'min_age_years', 'max_age_years'] as $requiredColumn) {
    if (!in_array($requiredColumn, $columnNames, true)) {
        $failures[] = 'Kolom workflow tidak tersedia: ' . $requiredColumn;
    }
}

$userId = (int) $pdo->query('SELECT id FROM users WHERE role = "admin" AND is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
$testCode = null;
foreach ($catalog as $code => $test) {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM reference_ranges WHERE test_code = :test_code');
    $statement->execute(['test_code' => $code]);
    if ((int) $statement->fetchColumn() === 0) {
        $testCode = $code;
        break;
    }
}
if ($userId < 1 || $testCode === null) {
    $failures[] = 'Akun admin atau parameter katalog kosong tidak tersedia untuk uji transaksi.';
}

if ($failures === []) {
    $test = $catalog[$testCode];
    $baseInput = [
        'test_code' => $testCode,
        'unit' => (string) ($test['unit'] ?? ''),
        'reference_note' => 'Verifikasi otomatis workflow; bukan nilai klinis.',
    ];
    if ($test['kind'] === 'numeric') {
        $baseInput['reference_value'] = '1 - 2';
    } else {
        $baseInput['normal_values'] = 'Negatif';
    }

    $pdo->beginTransaction();
    try {
        $fallbackId = ReferenceRangeRepository::create(array_merge($baseInput, ['sex' => 'ALL']), $userId);
        $fallbackStatement = $pdo->prepare('SELECT approval_status, is_active FROM reference_ranges WHERE id = :id');
        $fallbackStatement->execute(['id' => $fallbackId]);
        $fallback = $fallbackStatement->fetch();
        if ($fallback === false || $fallback['approval_status'] !== 'approved' || (int) $fallback['is_active'] !== 1) {
            $failures[] = 'Aturan baru tidak langsung tersimpan sebagai aktif.';
        }

        $femaleId = ReferenceRangeRepository::create(array_merge($baseInput, [
            'sex' => 'P',
            'min_age_years' => '18',
            'max_age_years' => '59',
        ]), $userId);

        foreach ([18, 59] as $boundaryAge) {
            $applicable = ReferenceRangeRepository::findApplicable($testCode, 'P', 7000, $boundaryAge);
            if ($applicable === false || (int) $applicable['id'] !== $femaleId) {
                $failures[] = 'Batas usia tahun inklusif tidak memilih aturan khusus Perempuan.';
            }
        }
        $afterBoundary = ReferenceRangeRepository::findApplicable($testCode, 'P', 22000, 60);
        if ($afterBoundary === false || (int) $afterBoundary['id'] !== $fallbackId) {
            $failures[] = 'Aturan semua jenis kelamin tidak digunakan sebagai fallback setelah batas usia.';
        }
        $maleFallback = ReferenceRangeRepository::findApplicable($testCode, 'L', 150, 30);
        if ($maleFallback === false || (int) $maleFallback['id'] !== $fallbackId) {
            $failures[] = 'Fallback Semua jenis kelamin tidak berlaku untuk pasien Laki-laki.';
        }

        try {
            ReferenceRangeRepository::create(array_merge($baseInput, [
                'sex' => 'P',
                'min_age_years' => '59',
                'max_age_years' => '70',
            ]), $userId);
            $failures[] = 'Rentang usia khusus yang overlap pada batas inklusif masih diterima.';
        } catch (ValidationException) {
            // Expected.
        }

        try {
            ReferenceRangeRepository::create(array_merge($baseInput, [
                'sex' => 'P',
                'min_age_days' => '100',
                'max_age_days' => '200',
            ]), $userId);
            $failures[] = 'Aturan usia hari masih dapat dicampurkan dengan aturan usia tahun yang aktif.';
        } catch (ValidationException) {
            // Expected.
        }

        $replacementId = ReferenceRangeRepository::create(array_merge($baseInput, [
            'sex' => 'P',
            'min_age_years' => '18',
            'max_age_years' => '59',
        ]), $userId);
        $firstVersion = $pdo->query('SELECT is_active FROM reference_ranges WHERE id = ' . (int) $femaleId)->fetchColumn();
        $replacement = ReferenceRangeRepository::findApplicable($testCode, 'P', 7000, 18);
        if ((int) $firstVersion !== 0 || $replacement === false || (int) $replacement['id'] !== $replacementId) {
            $failures[] = 'Aktivasi versi pengganti tidak mengarsipkan versi lama pada scope yang sama.';
        }

        $legacyDayId = ReferenceRangeRepository::create(array_merge($baseInput, [
            'sex' => 'L',
            'min_age_days' => '100',
            'max_age_days' => '200',
        ]), $userId);
        $legacyDayRule = ReferenceRangeRepository::findApplicable($testCode, 'L', 150, 0);
        if ($legacyDayRule === false || (int) $legacyDayRule['id'] !== $legacyDayId) {
            $failures[] = 'Aturan historis berbasis hari tidak lagi dapat digunakan.';
        }

    } finally {
        $pdo->rollBack();
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Verifikasi workflow nilai rujukan gagal:\n- " . implode("\n- ", array_unique($failures)) . "\n");
    exit(1);
}

echo "Workflow nilai rujukan valid: 67 parameter panel tersedia; penyimpanan langsung aktif, usia tahun inklusif, kompatibilitas usia hari, sex fallback, overlap, dan versioning terverifikasi tanpa menyisakan data uji.\n";
