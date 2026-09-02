<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/Env.php';
require ROOT_PATH . '/app/Database.php';

Env::load(ROOT_PATH . '/.env');

$checks = [];
$check = static function (string $level, string $label, string $detail) use (&$checks): void {
    $checks[] = compact('level', 'label', 'detail');
};

$check(version_compare(PHP_VERSION, '8.2.0', '>=') ? 'PASS' : 'FAIL', 'PHP 8.2+', PHP_VERSION);
foreach (['pdo_mysql', 'mbstring'] as $extension) {
    $check(extension_loaded($extension) ? 'PASS' : 'FAIL', "Ekstensi {$extension}", extension_loaded($extension) ? 'aktif' : 'tidak aktif');
}

$envExists = is_file(ROOT_PATH . '/.env');
$check($envExists ? 'PASS' : 'FAIL', 'Berkas .env', $envExists ? 'tersedia' : 'belum dibuat');

$appEnv = Env::get('APP_ENV', 'production');
$check($appEnv === 'production' ? 'PASS' : 'WARN', 'Mode produksi', "APP_ENV={$appEnv}");
$appUrl = Env::get('APP_URL', '');
$check(str_starts_with((string) $appUrl, 'https://') ? 'PASS' : 'WARN', 'HTTPS', $appUrl ?: 'APP_URL kosong');
$clinicName = Env::get('CLINIC_NAME', '');
$check($clinicName !== '' && $clinicName !== 'Nama Klinik' ? 'PASS' : 'WARN', 'Identitas klinik', 'CLINIC_NAME harus memakai nama resmi');

$logsDirectory = ROOT_PATH . '/storage/logs';
$check(is_dir($logsDirectory) && is_writable($logsDirectory) ? 'PASS' : 'FAIL', 'Folder log', 'storage/logs harus dapat ditulis PHP');

try {
    $pdo = Database::connection();
    $check('PASS', 'Koneksi database', 'tersambung');

    $tableExists = (bool) $pdo->query("SHOW TABLES LIKE 'schema_migrations'")->fetchColumn();
    if (!$tableExists) {
        $check('FAIL', 'Migrasi database', 'schema_migrations belum ada');
    } else {
        $executed = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $expectedFiles = glob(ROOT_PATH . '/database/migrations/*.sql') ?: [];
        $expected = array_map('basename', $expectedFiles);
        $missing = array_values(array_diff($expected, $executed));
        $check($missing === [] ? 'PASS' : 'FAIL', 'Migrasi database', $missing === [] ? count($expected) . ' migrasi terpasang' : 'belum: ' . implode(', ', $missing));
    }

    $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
    $check($adminCount > 0 ? 'PASS' : 'FAIL', 'Administrator aktif', "jumlah={$adminCount}");

    $rangeCount = (int) $pdo->query('SELECT COUNT(*) FROM reference_ranges WHERE is_active = 1')->fetchColumn();
    $check($rangeCount > 0 ? 'PASS' : 'WARN', 'Rentang referensi aktif', "jumlah={$rangeCount}; wajib disetujui penanggung jawab klinis");
    $legacyRangeCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM reference_ranges
         WHERE is_active = 1 AND approval_status = "approved"
           AND (reference_note IS NULL OR TRIM(reference_note) = "")'
    )->fetchColumn();
    $check($legacyRangeCount === 0 ? 'PASS' : 'WARN', 'Jejak persetujuan nilai rujukan', "aktif_tanpa_sumber={$legacyRangeCount}; perlu review ulang klinis");
    $draftRangeCount = (int) $pdo->query('SELECT COUNT(*) FROM reference_ranges WHERE approval_status = "draft"')->fetchColumn();
    $check($draftRangeCount === 0 ? 'PASS' : 'WARN', 'Antrean review nilai rujukan', "draft={$draftRangeCount}");
} catch (Throwable $exception) {
    $check('FAIL', 'Koneksi/database', $exception->getMessage());
}

$check('WARN', 'Backup dan restore', 'verifikasi manual diperlukan: backup terbaru dan satu uji restore');
$check('WARN', 'UAT dan persetujuan', 'verifikasi manual diperlukan: lembar UAT dan checklist go-live ditandatangani');

echo "Preflight aplikasi klinik\n\n";
foreach ($checks as $item) {
    echo sprintf("[%s] %s — %s\n", $item['level'], $item['label'], $item['detail']);
}

$failures = array_filter($checks, static fn (array $item): bool => $item['level'] === 'FAIL');
$warnings = array_filter($checks, static fn (array $item): bool => $item['level'] === 'WARN');
echo sprintf("\nRingkasan: %d gagal, %d peringatan.\n", count($failures), count($warnings));
exit($failures === [] ? 0 : 2);
