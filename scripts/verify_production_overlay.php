<?php

declare(strict_types=1);

// Reconstruct an in-place deployment from the actual older cPanel archive,
// rather than supplying unshipped files from the initial Git commit.
$project = dirname(__DIR__);
$fixedStage = realpath($argv[1] ?? '');
if ($fixedStage === false || !str_starts_with($fixedStage, $project . '/release-artifacts/production-')) {
    throw new RuntimeException('Provide the fixed generated release directory.');
}
require $project . '/app/Env.php';
Env::load($project . '/.env');
if (!in_array(Env::get('DB_HOST'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Overlay verification supports the local database only.');
}

function overlayWrite(string $root, string $name, string $bytes): void
{
    if (str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, '\\')) {
        throw new RuntimeException('Unsafe archive path.');
    }
    $target = $root . '/' . $name;
    if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
    if (file_put_contents($target, $bytes) === false) throw new RuntimeException('Cannot write QA artifact.');
}

function overlayRun(string $root, string $test): array
{
    $process = proc_open([PHP_BINARY, $root . '/tests/' . $test], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) throw new RuntimeException('Cannot run overlay check.');
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['status' => proc_close($process), 'output' => $output . $errors];
}

$old = new ZipArchive();
$deployed = new ZipArchive();
if ($old->open($project . '/klinik-core.zip') !== true
    || $deployed->open($project . '/klinik-production-code-2026-10-20.zip') !== true) {
    throw new RuntimeException('Missing original production/deployed archives.');
}
$root = $project . '/tmp/production-overlay-' . bin2hex(random_bytes(5));
mkdir($root, 0755, true);
for ($i = 0; $i < $old->numFiles; $i++) {
    $name = $old->getNameIndex($i);
    if (!is_string($name) || !str_starts_with($name, 'app/') || !str_ends_with($name, '.php')) continue;
    overlayWrite($root, $name, (string) $old->getFromIndex($i));
}
for ($i = 0; $i < $deployed->numFiles; $i++) {
    $name = $deployed->getNameIndex($i);
    if (!is_string($name) || str_ends_with($name, '/')) continue;
    if (preg_match('#^(app/|public_html/|vendor/|composer\.(?:json|lock)$|klinik-release-manifest\.json$)#', $name) !== 1) {
        throw new RuntimeException('Unexpected deployed archive entry.');
    }
    overlayWrite($root, $name, (string) $deployed->getFromIndex($i));
}
foreach (['PatientRepository.php', 'CumulativeResultRepository.php', 'LegacyImportService.php'] as $legacy) {
    if (hash_file('sha256', $root . '/app/' . $legacy) !== hash('sha256', (string) $old->getFromName('app/' . $legacy))) {
        throw new RuntimeException('Legacy production module was unexpectedly replaced.');
    }
}
$tests = ['examination_identity_regression.php', 'panel_reporting_regression.php', 'examination_pdf_regression.php', 'production_legacy_import_dry_run.php'];
foreach ($tests as $test) overlayWrite($root, 'tests/' . $test, file_get_contents($project . '/tests/' . $test));
overlayWrite($root, 'tests/fixtures/legacy_mcu_synthetic.csv', file_get_contents($project . '/tests/fixtures/legacy_mcu_synthetic.csv'));

$before = overlayRun($root, 'examination_identity_regression.php');
if ($before['status'] === 0 || !str_contains($before['output'], 'Undefined constant ExaminationRepository::DATA_RETENTION_START')) {
    throw new RuntimeException('Could not reproduce the reported production error: ' . $before['output']);
}
echo "Reproduced original production failure: Undefined constant ExaminationRepository::DATA_RETENTION_START.\n";

$beforeSource = (string) $deployed->getFromName('app/ExaminationRepository.php');
$fixedSource = file_get_contents($fixedStage . '/app/ExaminationRepository.php');
$compatibility = "    // Compatibility API used by the existing production history, cumulative and import modules.\n    public const DATA_RETENTION_START = '2026-01-01';\n\n";
if (substr_count($fixedSource, $compatibility) !== 1 || str_replace($compatibility, '', $fixedSource) !== $beforeSource) {
    throw new RuntimeException('Hotfix contains changes beyond the compatibility constant.');
}
overlayWrite($root, 'app/ExaminationRepository.php', $fixedSource);
foreach ($tests as $test) {
    $result = overlayRun($root, $test);
    echo $result['output'];
    if ($result['status'] !== 0) throw new RuntimeException('Overlay verification failed: ' . $test);
}

$patchPath = $project . '/klinik-hotfix-riwayat-2026-10-20.zip';
if (file_exists($patchPath)) throw new RuntimeException('Hotfix archive already exists; preserve it and choose a new filename.');
$patch = new ZipArchive();
if ($patch->open($patchPath, ZipArchive::CREATE) !== true) throw new RuntimeException('Cannot create hotfix archive.');
$patch->addFromString('app/ExaminationRepository.php', $fixedSource);
$patch->close();
$verifyPatch = new ZipArchive();
$verifyPatch->open($patchPath);
if ($verifyPatch->numFiles !== 1 || hash('sha256', (string) $verifyPatch->getFromName('app/ExaminationRepository.php')) !== hash('sha256', $fixedSource)) {
    throw new RuntimeException('Hotfix ZIP verification failed.');
}
$verifyPatch->close();
$report = [
    'result' => 'PASS', 'verified_at' => gmdate('c'), 'qa_overlay_directory' => $root,
    'original_error_reproduced' => true, 'legacy_modules_preserved' => true,
    'only_runtime_change' => 'ExaminationRepository::DATA_RETENTION_START compatibility constant',
    'checks' => ['identity_and_history', 'history_and_cumulative_rendering_with_anc', 'reports_and_analysis', 'seven_pdf_formats', 'legacy_import_dry_run'],
    'patch' => basename($patchPath), 'patch_sha256' => hash_file('sha256', $patchPath),
];
file_put_contents($fixedStage . '/overlay-verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "Production overlay: PASS; the one-file hotfix preserves all legacy production modules.\n";
echo 'Hotfix ZIP: ' . $patchPath . PHP_EOL;
