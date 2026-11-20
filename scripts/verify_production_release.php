<?php

declare(strict_types=1);

$project = dirname(__DIR__);
$stage = realpath($argv[1] ?? '');
if ($stage === false || !str_starts_with($stage, $project . '/release-artifacts/production-')) {
    throw new RuntimeException('Provide the generated release directory.');
}

if (($argv[2] ?? '') === '--guards') {
    require $stage . '/app/bootstrap.php';
    set_exception_handler(static function (Throwable $error): void {
        fwrite(STDERR, (string) $error . PHP_EOL);
        exit(1);
    });
    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $pdo = Database::connection();
    foreach (['patients', 'examinations', 'examination_panel_selections', 'audit_logs'] as $table) {
        $definition = (string) $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
        $lines = array_filter(explode("\n", $definition), static fn(string $line): bool => preg_match('/^\s*CONSTRAINT .* FOREIGN KEY/', $line) !== 1);
        $definition = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', implode("\n", $lines));
        $pdo->exec((string) preg_replace('/,(\s*\))/', '$1', $definition));
    }
    $pdo->exec('ALTER TABLE patients AUTO_INCREMENT = 2000000000');
    $pdo->exec('ALTER TABLE examinations AUTO_INCREMENT = 2000000000');
    $userId = (int) $pdo->query('SELECT id FROM users WHERE role = "admin" AND is_active = 1 LIMIT 1')->fetchColumn();
    $assert($userId > 0, 'Local active admin required.');
    $pdo->exec('INSERT INTO patients (medical_record_number, full_name, sex, birth_date, created_at, updated_at) VALUES ("__RELEASE_GUARD", "Pasien sintetis rilis", "P", "1996-06-06", UTC_TIMESTAMP(), UTC_TIMESTAMP())');
    $patientId = (int) $pdo->lastInsertId();
    $id = ExaminationRepository::create([
        'patient_id' => $patientId, 'lab_number' => '__RELEASE_GUARD_2025',
        'examination_type' => 'mcu_general', 'examined_on' => '2025-12-31',
        'examined_time' => '08:00', 'room' => 'Uji rilis', 'panel_codes' => ['hematology_complete'],
    ], $userId);
    $assert(ExaminationRepository::find($id) !== false, 'The release hides examinations before 2026.');
    $assert(count(PatientRepository::examinations($patientId)) === 1, 'Patient history lost the 2025 examination.');
    $pdo->exec('UPDATE examinations SET status = "completed"');
    $records = ReportRepository::search(['date_from' => '2025-01-01', 'date_to' => '2025-12-31', 'q' => '__RELEASE_GUARD']);
    $assert(count($records) === 1, 'Rekap hides the 2025 examination.');
    $dashboard = AnalyticsRepository::dashboard(['date_from' => '2025-01-01', 'date_to' => '2025-12-31', 'status' => 'completed'], $pdo);
    $assert($dashboard['summary']['total_examinations'] === 1, 'Analysis hides the 2025 examination.');
    try {
        PatientRepository::deletePermanently($patientId, '__RELEASE_GUARD', $userId);
        throw new RuntimeException('Patient with examination history could be deleted.');
    } catch (ValidationException $error) {
        $assert(str_contains(implode(' ', $error->errors()), 'riwayat pemeriksaan'), 'Patient deletion failed for an unexpected reason.');
    }
    $assert(ExaminationRepository::find($id) !== false && PatientRepository::find($patientId) !== false, 'Patient/history deletion guard failed.');
    $assert(method_exists(ExaminationRepository::class, 'deleteExamination'), 'Existing examination deletion method missing.');
    echo "Release guards: PASS; 2025 records visible in history/Rekap/Analysis and patient history cannot be deleted. Temporary tables only.\n";
    exit;
}

require $project . '/app/Env.php';
Env::load($project . '/.env');
if (($qaEnv = getenv('KLINIK_RELEASE_QA_ENV')) !== false) Env::load($qaEnv);
if (!in_array(Env::get('DB_HOST'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Release verification only supports the local database.');
}

function releaseRun(array $command, string $directory, bool $quiet = false): void
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory);
    if (!is_resource($process)) throw new RuntimeException('Cannot execute release check.');
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if (!$quiet || $status !== 0) echo $output . $errors;
    // The application's exception handler can report an error yet exit with 0.
    if ($status !== 0 || str_contains($output, 'Application error:') || str_contains($errors, 'Stack trace:')) {
        throw new RuntimeException('Release check failed: ' . basename($command[1]));
    }
}

$manifest = json_decode(file_get_contents($stage . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$archivePath = $project . '/' . ($manifest['code_archive'] ?? 'klinik-production-code-2026-10-20.zip');
foreach ($manifest['files_sha256'] as $relative => $hash) {
    if (hash_file('sha256', $stage . '/' . $relative) !== $hash) throw new RuntimeException('Manifest mismatch: ' . $relative);
    if (str_ends_with($relative, '.php') && !str_starts_with($relative, 'vendor/')) {
        releaseRun([PHP_BINARY, '-l', $stage . '/' . $relative], $stage, true);
    }
}
echo "Manifest and runtime PHP syntax: PASS.\n";
releaseRun([PHP_BINARY, __FILE__, $stage, '--guards'], $stage);
foreach ([
    'widal_dropdown_regression.php', 'immunoserology_dropdown_regression.php',
    'panel_defaults_regression.php', 'anc_panel_regression.php',
    'urine_negative_reference_regression.php', 'urine_qualitative_migration_regression.php',
    'examination_identity_regression.php', 'panel_reporting_regression.php',
    'examination_pdf_regression.php',
    'examination_deletion_regression.php',
] as $test) {
    releaseRun([PHP_BINARY, $stage . '/tests/' . $test], $stage);
}
$archive = new ZipArchive();
if ($archive->open($archivePath) !== true) {
    throw new RuntimeException('Release ZIP cannot be opened.');
}
foreach ($manifest['files_sha256'] as $relative => $hash) {
    $bytes = $archive->getFromName($relative);
    if ($bytes === false || hash('sha256', $bytes) !== $hash) throw new RuntimeException('ZIP payload mismatch: ' . $relative);
}
if ($archive->numFiles !== count($manifest['files_sha256']) + 1) throw new RuntimeException('Unexpected ZIP entries.');
for ($i = 0; $i < $archive->numFiles; $i++) {
    $name = $archive->getNameIndex($i);
    if ($name === false || preg_match('#(^|/)(\.env[^/]*|storage|database|tests|\.git)(/|$)|\.sql(?:\.gz)?$#', $name)) {
        throw new RuntimeException('Excluded path in release archive.');
    }
}
$archive->close();
file_put_contents($stage . '/verification.json', json_encode([
    'result' => 'PASS', 'verified_at' => gmdate('c'),
    'code_archive_sha256' => hash_file('sha256', $archivePath),
    'checks' => ['runtime_syntax', 'history_before_2026', 'patient_deletion_guard',
        'widal', 'rapid_dropdowns', 'panel_defaults', 'anc', 'urine_references',
        'migration_039', 'identity_without_doctor', 'reporting_and_analysis',
        'seven_pdf_formats', 'examination_deletion_requests', 'zip_manifest_and_excluded_paths'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "Production release verification: PASS; ZIP hashes match and excluded paths are absent.\n";
