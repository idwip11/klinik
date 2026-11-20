<?php

declare(strict_types=1);

// Verify against the actual deployed combination: September core, October update,
// and the already-installed history hotfix. Only publish the patch after checks pass.
$project = dirname(__DIR__);
$stage = realpath($argv[1] ?? '');
if ($stage === false || !str_starts_with($stage, $project . '/release-artifacts/production-')) {
    throw new RuntimeException('Provide the generated corrected release directory.');
}
require $project . '/app/Env.php';
Env::load($project . '/.env');
if (($qaEnv = getenv('KLINIK_RELEASE_QA_ENV')) !== false) Env::load($qaEnv);
if (!in_array(Env::get('DB_HOST'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Patch verification supports the local database only.');
}
function deletionWrite(string $root, string $relative, string $bytes): void
{
    if (str_starts_with($relative, '/') || str_contains($relative, '..') || str_contains($relative, '\\')) {
        throw new RuntimeException('Unsafe archive entry.');
    }
    $target = $root . '/' . $relative;
    if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
    if (file_put_contents($target, $bytes) === false) throw new RuntimeException('Cannot write QA artifact.');
}
function deletionRun(string $root, string $test, array $arguments = []): array
{
    $process = proc_open(array_merge([PHP_BINARY, $root . '/tests/' . $test], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) throw new RuntimeException('Cannot run patch verification.');
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['status' => proc_close($process), 'output' => $output . $errors];
}
$root = $project . '/tmp/deletion-overlay-' . bin2hex(random_bytes(5));
mkdir($root, 0755, true);
foreach (['klinik-core.zip', 'klinik-production-code-2026-10-20.zip', 'klinik-hotfix-riwayat-2026-10-20.zip'] as $i => $archiveName) {
    $archive = new ZipArchive();
    if ($archive->open($project . '/' . $archiveName) !== true) throw new RuntimeException('Missing production archive: ' . $archiveName);
    for ($n = 0; $n < $archive->numFiles; $n++) {
        $name = $archive->getNameIndex($n);
        if (!is_string($name) || str_ends_with($name, '/')) continue;
        if ($i === 0 && (!str_starts_with($name, 'app/') || !str_ends_with($name, '.php'))) continue;
        if (preg_match('#^(app/|public_html/|vendor/|composer\.(?:json|lock)$|klinik-release-manifest\.json$)#', $name) !== 1) {
            throw new RuntimeException('Unexpected production archive entry.');
        }
        deletionWrite($root, $name, (string) $archive->getFromIndex($n));
    }
    $archive->close();
}
$tests = ['examination_deletion_regression.php', 'examination_identity_regression.php', 'panel_reporting_regression.php', 'examination_pdf_regression.php', 'production_legacy_import_dry_run.php'];
foreach ($tests as $test) deletionWrite($root, 'tests/' . $test, file_get_contents($project . '/tests/' . $test));
deletionWrite($root, 'tests/fixtures/legacy_mcu_synthetic.csv', file_get_contents($project . '/tests/fixtures/legacy_mcu_synthetic.csv'));
$before = deletionRun($root, 'examination_deletion_regression.php', ['success']);
if ($before['status'] === 0 || !str_contains($before['output'], 'Unexpected response for success: 404')) {
    throw new RuntimeException('Could not reproduce deletion 404: ' . $before['output']);
}
echo "Reproduced production bug: valid delete POST returns 404; fixture remains intact.\n";

$blocks = [
    'app/ExaminationRepository.php' => ["\n    public static function deleteExamination(", "\n    private static function reserveNextSequence"],
    'public_html/index.php' => ["if (preg_match('#^/examinations/(\\d+)/delete\$#', \$path, \$matches)", "if (\$method === 'GET' && \$path === '/settings/reference-ranges')"],
];
$payload = [];
foreach ($blocks as $relative => [$startMarker, $endMarker]) {
    $beforeSource = file_get_contents($root . '/' . $relative);
    $fixed = file_get_contents($stage . '/' . $relative);
    if (substr_count($fixed, $startMarker) !== 1 || substr_count($fixed, $endMarker) !== 1) throw new RuntimeException('Ambiguous patch block.');
    $start = strpos($fixed, $startMarker);
    $end = strpos($fixed, $endMarker, $start);
    if ($end === false || substr_replace($fixed, '', $start, $end - $start) !== $beforeSource) {
        throw new RuntimeException('Patch changes unrelated code in ' . $relative);
    }
    $payload[$relative] = $fixed;
    deletionWrite($root, $relative, $fixed);
}
foreach ($tests as $test) {
    $result = deletionRun($root, $test);
    echo $result['output'];
    if ($result['status'] !== 0) throw new RuntimeException('Patched production overlay failed: ' . $test);
}
$patchPath = $project . '/klinik-hotfix-hapus-pemeriksaan-2026-10-22.zip';
if (file_exists($patchPath)) throw new RuntimeException('Hotfix already exists; preserve it and choose a new filename.');
$patch = new ZipArchive();
if ($patch->open($patchPath, ZipArchive::CREATE) !== true) throw new RuntimeException('Cannot create deletion hotfix.');
foreach ($payload as $relative => $bytes) $patch->addFromString($relative, $bytes);
$patch->close();
$verify = new ZipArchive();
if ($verify->open($patchPath) !== true || $verify->numFiles !== count($payload)) throw new RuntimeException('Unexpected patch entries.');
foreach ($payload as $relative => $bytes) {
    if ($verify->getFromName($relative) !== $bytes) throw new RuntimeException('Patch ZIP mismatch.');
}
$verify->close();
file_put_contents($stage . '/deletion-overlay-verification.json', json_encode([
    'result' => 'PASS', 'verified_at' => gmdate('c'), 'qa_overlay_directory' => $root,
    'production_404_reproduced' => true, 'only_added_code' => ['existing_delete_method', 'existing_delete_post_route'],
    'checks' => $tests, 'patch' => basename($patchPath), 'sha256' => hash_file('sha256', $patchPath),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo 'Deletion hotfix verified: ' . $patchPath . PHP_EOL;
