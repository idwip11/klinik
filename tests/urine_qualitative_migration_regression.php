<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string) $error . PHP_EOL);
    exit(1);
});

function migrationAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

// Shadow real tables for this connection so the migration's COMMIT statements
// cannot change any real configuration or patient results during this test.
$pdo = Database::connection();
$codes = ['URINE_UROBILINOGEN', 'URINE_BILIRUBIN', 'URINE_OCCULT_BLOOD', 'URINE_KETONE'];
$quotedCodes = implode(',', array_map([$pdo, 'quote'], $codes));
$fixtures = [
    'users' => $pdo->query('SELECT * FROM users WHERE role = "admin" AND is_active = 1 ORDER BY id LIMIT 1')->fetchAll(),
    'test_parameters' => $pdo->query('SELECT * FROM test_parameters WHERE code IN (' . $quotedCodes . ')')->fetchAll(),
    'reference_ranges' => $pdo->query('SELECT * FROM reference_ranges WHERE test_code IN (' . $quotedCodes . ') OR test_code = "ANC_URINE_ALBUMIN"')->fetchAll(),
];
migrationAssert(count($fixtures['users']) === 1 && count($fixtures['test_parameters']) === 4, 'Fixture admin/parameter tidak lengkap.');

foreach ($fixtures as $table => $rows) {
    $definition = (string) $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
    $lines = array_filter(explode("\n", $definition), static fn(string $line): bool => preg_match('/^\s*CONSTRAINT .* FOREIGN KEY/', $line) !== 1);
    $definition = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', implode("\n", $lines));
    $pdo->exec((string) preg_replace('/,(\s*\))/', '$1', $definition));
    foreach ($rows as $row) {
        $columns = '`' . implode('`,`', array_keys($row)) . '`';
        $placeholders = implode(',', array_fill(0, count($row), '?'));
        $pdo->prepare('INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . $placeholders . ')')->execute(array_values($row));
    }
}
$pdo->exec('UPDATE reference_ranges SET reference_note = "Fixture rujukan sebelumnya" WHERE test_code IN (' . $quotedCodes . ')');
$pdo->exec('UPDATE test_parameters SET unit = "mg/dL"');
$pdo->exec('UPDATE users SET is_active = 0');

$migration = file_get_contents(ROOT_PATH . '/database/migrations/039_urine_qualitative_reference_ranges.sql');
migrationAssert($migration !== false, 'SQL migrasi tidak ditemukan.');
$applyMigration = static function () use ($pdo, $migration): void {
    try {
        foreach (preg_split('/;\s*(?:\R|$)/', $migration) ?: [] as $sql) {
            if (trim($sql) !== '') $pdo->exec($sql);
        }
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
};
$before = $pdo->query('SELECT * FROM reference_ranges ORDER BY id')->fetchAll();
$parametersBefore = $pdo->query('SELECT * FROM test_parameters ORDER BY id')->fetchAll();
$applyMigration();
migrationAssert($before === $pdo->query('SELECT * FROM reference_ranges ORDER BY id')->fetchAll(), 'Tidak ada admin aktif: rujukan lama tidak boleh diarsipkan.');
migrationAssert($parametersBefore === $pdo->query('SELECT * FROM test_parameters ORDER BY id')->fetchAll(), 'Tidak ada admin aktif: satuan tidak boleh berubah.');

$pdo->exec('UPDATE users SET is_active = 1');
$ancBefore = $pdo->query('SELECT * FROM reference_ranges WHERE test_code = "ANC_URINE_ALBUMIN" ORDER BY id')->fetchAll();
$maximumVersions = [];
foreach ($codes as $code) {
    $maximumVersions[$code] = (int) $pdo->query('SELECT MAX(version) FROM reference_ranges WHERE test_code = ' . $pdo->quote($code))->fetchColumn();
}
for ($run = 1; $run <= 2; $run++) {
    $applyMigration();
    foreach ($codes as $code) {
        $active = $pdo->query('SELECT * FROM reference_ranges WHERE test_code = ' . $pdo->quote($code) . ' AND is_active = 1 AND approval_status = "approved"')->fetchAll();
        migrationAssert(count($active) === 1, "$code: harus hanya ada satu versi aktif.");
        $range = $active[0];
        migrationAssert((int) $range['version'] === $maximumVersions[$code] + $run, "$code: versi rujukan tidak bertambah.");
        migrationAssert($range['sex'] === 'ALL' && $range['unit'] === null && $range['result_kind'] === 'qualitative', "$code: populasi/jenis/satuan tidak sesuai.");
        foreach (['min_age_days', 'max_age_days', 'min_age_years', 'max_age_years', 'lower_bound', 'upper_bound'] as $column) {
            migrationAssert($range[$column] === null, "$code: batas umur atau angka lama masih dipakai.");
        }
        $expected = $code === 'URINE_UROBILINOGEN' ? 'Normal' : 'Negatif';
        migrationAssert(json_decode($range['normal_values'], true) === [$expected], "$code: nilai rujukan salah.");
        migrationAssert($pdo->query('SELECT unit FROM test_parameters WHERE code = ' . $pdo->quote($code))->fetchColumn() === null, "$code: satuan katalog belum diperbarui.");
    }
}
foreach ($before as $original) {
    $after = $pdo->query('SELECT * FROM reference_ranges WHERE id = ' . (int) $original['id'])->fetch();
    migrationAssert($after !== false, 'Versi rujukan lama terhapus.');
    unset($original['is_active'], $after['is_active']);
    migrationAssert($original === $after, 'Isi atau metadata versi rujukan lama berubah.');
}
migrationAssert($ancBefore === $pdo->query('SELECT * FROM reference_ranges WHERE test_code = "ANC_URINE_ALBUMIN" ORDER BY id')->fetchAll(), 'Rujukan ANC ikut berubah.');
echo "PASS: migrasi 039, versi lama tetap tersimpan, tanpa admin tidak mengarsipkan, pengulangan aman, dan ANC tetap utuh. Hanya tabel temporer.\n";
