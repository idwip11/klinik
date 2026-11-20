<?php /** @var string $pageTitle */ ?>
<?php $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= View::e($pageTitle) ?> · Klinik Lab</title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<?php if (Auth::check()): ?>
    <header class="site-header">
        <a class="brand" href="/dashboard" aria-label="Beranda Klinik Rawat Inap Basmalah Medika">
            <img class="brand-logo" src="/assets/logo-basmalah-medika.jpeg" alt="Logo Klinik Rawat Inap Basmalah Medika">
            <span class="brand-wordmark">
                <span class="brand-kicker">Klinik Rawat Inap</span>
                <span class="brand-name">Basmalah Medika</span>
            </span>
        </a>
        <nav class="primary-nav" aria-label="Navigasi utama">
            <a class="nav-item<?= $currentPath === '/dashboard' ? ' is-active' : '' ?>" href="/dashboard" aria-label="Beranda" title="Beranda">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg><span>Beranda</span>
            </a>
            <a class="nav-item<?= str_starts_with($currentPath, '/patients') ? ' is-active' : '' ?>" href="/patients" aria-label="Pasien" title="Pasien">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.1 3.6-7 8-7s8 2.9 8 7"/></svg><span>Pasien</span>
            </a>
            <?php if (in_array(Auth::user()['role'] ?? '', ['admin', 'perawat'], true)): ?>
                <a class="nav-item<?= str_starts_with($currentPath, '/reports') ? ' is-active' : '' ?>" href="/reports" aria-label="Rekap" title="Rekap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg><span>Rekap</span>
                </a>
            <?php endif; ?>
            <?php if ((Auth::user()['role'] ?? '') === 'admin'): ?>
                <a class="nav-item<?= str_starts_with($currentPath, '/settings/reference-ranges') ? ' is-active' : '' ?>" href="/settings/reference-ranges" aria-label="Nilai rujukan" title="Konfigurasi nilai rujukan">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5M4 17h16M7 14l4-4 3 2 5-6"/><circle cx="11" cy="10" r="1"/><circle cx="14" cy="12" r="1"/><circle cx="19" cy="6" r="1"/></svg><span>Nilai rujukan</span>
                </a>
                <a class="nav-item<?= str_starts_with($currentPath, '/analytics') ? ' is-active' : '' ?>" href="/analytics" aria-label="Analisis" title="Analisis laboratorium">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V5M4 19h16M7 16l3-4 3 2 5-7"/><circle cx="7" cy="16" r="1"/><circle cx="10" cy="12" r="1"/><circle cx="13" cy="14" r="1"/><circle cx="18" cy="7" r="1"/></svg><span>Analisis</span>
                </a>
                <a class="nav-item<?= str_starts_with($currentPath, '/settings/users') ? ' is-active' : '' ?>" href="/settings/users" aria-label="Pengguna" title="Pengguna">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.2 2.7-5.5 6-5.5s6 2.3 6 5.5M16 5.5a3 3 0 0 1 0 5M17 14.8c2.4.2 4 1.9 4 4.2"/></svg><span>Pengguna</span>
                </a>
            <?php endif; ?>
            <form method="post" action="/logout" class="inline-form">
                <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
                <button type="submit" class="nav-item nav-logout" aria-label="Keluar" title="Keluar">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h5M14 8l4 4-4 4M18 12H8"/></svg><span>Keluar</span>
                </button>
            </form>
        </nav>
    </header>
<?php endif; ?>
<main class="container<?= Auth::check() ? '' : ' auth-container' ?><?= isset($pageClass) ? ' ' . View::e($pageClass) : '' ?>">
    <?php if (is_array($flash)): ?>
        <div class="notice notice-<?= View::e($flash['type']) ?>" role="status"><?= View::e($flash['message']) ?></div>
    <?php endif; ?>
    <?php require $viewFile; ?>
</main>
</body>
</html>
