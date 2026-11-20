<?php

declare(strict_types=1);

// Generate a separate deployment snapshot; never rewrite the working copy.
$project = dirname(__DIR__);
$label = $argv[1] ?? 'production-2026-10-20';
if (preg_match('/^production-[0-9-]+(?:-r[0-9]+)?$/D', $label) !== 1) {
    throw new RuntimeException('Invalid release label.');
}
$stage = $project . '/release-artifacts/' . $label;
if (file_exists($stage)) throw new RuntimeException('Release directory already exists; use a new -rN label.');
mkdir($stage, 0755, true);

function releaseGitSource(string $project, string $path): string
{
    $process = proc_open(['git', 'show', '6d226bb:' . $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $project);
    if (!is_resource($process)) throw new RuntimeException('Cannot read the release baseline.');
    $source = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException($error);
    return $source;
}

function releaseReplace(string $source, string $before, string $after, int $expected = 1): string
{
    $source = str_replace($before, $after, $source, $count);
    if ($count !== $expected) throw new RuntimeException('Unexpected release transformation: ' . substr($before, 0, 90));
    return $source;
}

function releaseSection(string $source, string $from, string $to, ?string $replacement = null): string
{
    if (substr_count($source, $from) !== 1 || substr_count($source, $to) !== 1) {
        throw new RuntimeException('Release section is ambiguous: ' . $from);
    }
    $start = strpos($source, $from);
    $end = strpos($source, $to, $start);
    if ($end === false) throw new RuntimeException('Release section end not found.');
    return substr($source, 0, $start) . ($replacement ?? '') . substr($source, $end);
}

$archiveAppFiles = [
    'app/AnalyticsAggregateExporter.php', 'app/AnalyticsRepository.php',
    'app/ExaminationPanelCatalogRepository.php', 'app/ExaminationParameterResultRepository.php',
    'app/ExaminationRepository.php', 'app/HistoricalReferenceEvaluator.php',
    'app/ReferenceEvaluator.php', 'app/ReferenceRangeRepository.php',
    'app/ReportExcelExporter.php', 'app/ReportRepository.php', 'app/View.php', 'app/bootstrap.php',
    'app/ExaminationExcelExporter.php', 'app/ExaminationPdfExporter.php', 'app/WidalResultOptions.php',
    'app/views/analytics/help.php', 'app/views/analytics/index.php',
    'app/views/examinations/create.php', 'app/views/examinations/dl_dll.php',
    'app/views/examinations/forms/blood.php', 'app/views/examinations/forms/mcu.php',
    'app/views/examinations/forms/panels.php', 'app/views/examinations/panel_print.php',
    'app/views/examinations/preview.php', 'app/views/examinations/preview_drug_screen.php',
    'app/views/examinations/show.php', 'app/views/examinations/patient_identity.php',
    'app/views/examinations/pdf.css', 'app/views/layout.php',
    'app/views/patients/cumulative_results.php', 'app/views/reports/index.php',
    'app/views/settings/reference_ranges.php', 'app/views/verification/drug.php',
];
$transformed = [];
$entries = [];
$add = static function (string $relative, string $bytes, bool $includeInArchive = true) use ($stage, &$entries): void {
    $target = $stage . '/' . $relative;
    if (!is_dir(dirname($target))) mkdir(dirname($target), 0755, true);
    if (file_put_contents($target, $bytes) === false) throw new RuntimeException('Cannot write release file.');
    if ($includeInArchive) $entries[$relative] = hash('sha256', $bytes);
};

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (!$file->isFile() || $file->isLink()) continue;
    $relative = substr($file->getPathname(), strlen($project) + 1);
    $includeInArchive = in_array($relative, $archiveAppFiles, true);
    $source = $includeInArchive && $relative !== 'app/views/layout.php'
        ? file_get_contents($file->getPathname()) : releaseGitSource($project, $relative);
    if ($relative === 'app/views/layout.php') {
        $source = releaseReplace($source, 'href="/assets/app.css"', 'href="/assets/app.css?v=20261020-r1"');
    }

    if ($relative === 'app/ExaminationRepository.php') {
        $source = releaseReplace($source, "    public const DATA_RETENTION_START = '2026-01-01';\n\n", "    // Compatibility API used by the existing production history, cumulative and import modules.\n    public const DATA_RETENTION_START = '2026-01-01';\n\n");
        // Existing production history forms already submit to this method.
        // Preserve its validation and retention policy while transforming other queries.
        $deleteStart = strpos($source, "\n    public static function deleteExamination(");
        if ($deleteStart === false) throw new RuntimeException('Examination deletion method missing.');
        $deleteEnd = strpos($source, "\n    private static function reserveNextSequence", $deleteStart);
        if ($deleteEnd === false) throw new RuntimeException('Examination deletion method end missing.');
        $deleteMethod = substr($source, $deleteStart, $deleteEnd - $deleteStart);
        $source = releaseSection($source, "\n    public static function deleteExamination(", "\n    private static function reserveNextSequence");
        $source = str_replace('assertRetainedExamDate', 'assertExamDate', $source);
        $source = releaseReplace($source, "        if (\$examinedOn < self::DATA_RETENTION_START) {\n            throw new ValidationException(['examined_on' => 'Tanggal periksa harus 01/01/2026 atau setelahnya.']);\n        }\n", '');
        $source = releaseReplace($source, "               AND e.examined_on >= :retention_start\n", '');
        $source = releaseReplace($source, "                   AND examined_on >= :retention_start\n", '');
        $source = releaseReplace($source, "            'retention_start' => self::DATA_RETENTION_START,\n", '');
        $source = releaseReplace($source, "['id' => \$id, 'retention_start' => self::DATA_RETENTION_START]", "['id' => \$id]");
        $source = releaseReplace($source, "\n    private static function reserveNextSequence", $deleteMethod . "\n    private static function reserveNextSequence");
    }
    if ($relative === 'app/AnalyticsRepository.php') {
        $source = releaseReplace($source, "    private const DATA_RETENTION_START = ExaminationRepository::DATA_RETENTION_START;\n", '');
        $source = releaseSection($source, "        if (\$dateTo !== null && \$dateTo < self::DATA_RETENTION_START)", "        \$type = self::inputString(\$input['type'] ?? '');");
        $source = releaseReplace($source, "            'e.examined_on >= :retention_start',\n", '');
        $source = releaseReplace($source, "            'retention_start' => self::DATA_RETENTION_START,\n", '');
    }
    if ($relative === 'app/ReportRepository.php') {
        $source = releaseReplace($source, "        // Rekap operasional hanya menggunakan pemeriksaan mulai tahun 2026.\n", '');
        $source = releaseReplace($source, "['1 = 1', 'p.archived_at IS NULL', 'e.examined_on >= :retention_start']", "['1 = 1', 'p.archived_at IS NULL']");
        $source = releaseReplace($source, "\$parameters = ['retention_start' => ExaminationRepository::DATA_RETENTION_START];", '$parameters = [];');
    }
    if (in_array($relative, ['app/views/examinations/create.php', 'app/views/examinations/show.php', 'app/views/reports/index.php', 'app/views/analytics/index.php'], true)) {
        $source = str_replace(' min="<?= ExaminationRepository::DATA_RETENTION_START ?>"', '', $source);
        $source = str_replace("'<?= ExaminationRepository::DATA_RETENTION_START ?>'", "''", $source);
    }
    if ($includeInArchive && $source !== file_get_contents($file->getPathname())) $transformed[] = $relative;
    $add($relative, $source, $includeInArchive);
}

$index = file_get_contents($project . '/public/index.php');
$baselineIndex = releaseGitSource($project, 'public/index.php');
$routeStart = "if (preg_match('#^/patients/(\\d+)/archive\$#', \$path, \$matches)";
$routeEnd = "if (\$method === 'GET' && \$path === '/examinations/create')";
$baselineStart = strpos($baselineIndex, $routeStart);
$baselineEnd = strpos($baselineIndex, $routeEnd);
if ($baselineStart === false || $baselineEnd === false) throw new RuntimeException('Baseline patient routes not found.');
$index = releaseSection($index, $routeStart, $routeEnd, substr($baselineIndex, $baselineStart, $baselineEnd - $baselineStart));
$add('public_html/index.php', $index);
$transformed[] = 'public_html/index.php';

foreach (['app.css', 'logo-basmalah-medika.jpeg', 'header-basmalah.png', 'footer-basmalah.png'] as $asset) {
    $add('public_html/assets/' . $asset, file_get_contents($project . '/public/assets/' . $asset));
}
foreach (['composer.json', 'composer.lock'] as $file) $add($file, file_get_contents($project . '/' . $file));
$vendor = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project . '/vendor', FilesystemIterator::SKIP_DOTS));
foreach ($vendor as $file) {
    if (!$file->isFile() || $file->isLink()) continue;
    $relative = substr($file->getPathname(), strlen($project) + 1);
    if (str_contains($relative, '/.git/') || $file->getBasename() === '.DS_Store') continue;
    $add($relative, file_get_contents($file->getPathname()));
}

// Preserve existing deletion endpoints; exclude unrelated retention changes.
foreach (array_keys($entries) as $relative) {
    if (!str_starts_with($relative, 'app/') && $relative !== 'public_html/index.php') continue;
    $source = file_get_contents($stage . '/' . $relative);
    // Keep the public declaration for existing production modules; new code must
    // not acquire additional retention checks through this packaging step.
    if ($relative === 'app/ExaminationRepository.php') {
        $source = str_replace("    public const DATA_RETENTION_START = '2026-01-01';\n", '', $source);
        $source = releaseSection($source, "\n    public static function deleteExamination(", "\n    private static function reserveNextSequence");
    }
    foreach (['DATA_RETENTION_START', 'retention_start', 'before_retention_cutoff', 'doctor_name'] as $forbidden) {
        if (str_contains($source, $forbidden)) throw new RuntimeException('Excluded feature remains in ' . $relative . ': ' . $forbidden);
    }
}
if (!str_contains($index, "#^/examinations/(\\d+)/delete\$#") || !str_contains($index, 'ExaminationRepository::deleteExamination(')) {
    throw new RuntimeException('Examination deletion route missing from release.');
}
if (!str_contains(file_get_contents($stage . '/app/PatientRepository.php'), 'Pasien yang sudah memiliki riwayat pemeriksaan tidak dapat dihapus permanen.')) {
    throw new RuntimeException('Patient history deletion guard missing.');
}

// QA files stay outside the archive, and never include .env or local DB rows.
mkdir($stage . '/tests', 0755, true);
foreach (glob($project . '/tests/*.php') ?: [] as $test) copy($test, $stage . '/tests/' . basename($test));
mkdir($stage . '/database/migrations', 0755, true);
copy($project . '/database/migrations/039_urine_qualitative_reference_ranges.sql', $stage . '/database/migrations/039_urine_qualitative_reference_ranges.sql');

ksort($entries);
$archivePath = $project . '/klinik-production-code-' . substr($label, strlen('production-')) . '.zip';
$manifest = [
    'release' => $label,
    'baseline_for_unrelated_files' => '6d226bb',
    'layout' => 'Extract app/, vendor/, composer.* and public_html/ into /home/basg2576',
    'migration_prerequisite' => '031-039 applied through phpMyAdmin during the deployment window',
    'minimum_php' => '8.2',
    'code_archive' => basename($archivePath),
    'compatibility_constants' => ['ExaminationRepository::DATA_RETENTION_START' => '2026-01-01'],
    'required_extensions' => ['pdo_mysql', 'gd', 'dom', 'mbstring', 'zip'],
    'transformed_files' => $transformed,
    'files_sha256' => $entries,
];
file_put_contents($stage . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
$archive = new ZipArchive();
if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create release archive.');
foreach (array_keys($entries) as $relative) $archive->addFile($stage . '/' . $relative, $relative);
$archive->addFile($stage . '/manifest.json', 'klinik-release-manifest.json');
$archive->close();
echo 'Release directory: ' . $stage . PHP_EOL;
echo 'Code archive: ' . $archivePath . PHP_EOL;
echo count($entries) . ' runtime files; no .env, database rows, SQL dumps, storage or server configuration.' . PHP_EOL;
