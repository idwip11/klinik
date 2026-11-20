<?php

declare(strict_types=1);

// Each request runs in a child process with connection-scoped temporary tables.
// No application data, audit rows or number sequences in real tables are changed.
$root = dirname(__DIR__);
$case = $argv[1] ?? null;
$cases = ['success', 'wrong_lab', 'forbidden', 'csrf', 'get', 'missing'];
if ($case === null) {
    foreach ($cases as $requestCase) {
        $process = proc_open([PHP_BINARY, __FILE__, $requestCase], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        if (!is_resource($process)) throw new RuntimeException('Cannot start deletion request check.');
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        echo $output . $errors;
        if ($status !== 0) exit(1);
    }
    echo "Examination deletion: PASS; real POST route, confirmation, role, CSRF, GET and missing ID. Temporary tables only.\n";
    exit;
}
if (!in_array($case, $cases, true)) throw new RuntimeException('Unknown request case.');
require $root . '/app/bootstrap.php';
if (($qaEnv = getenv('KLINIK_RELEASE_QA_ENV')) !== false) Env::load($qaEnv);
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string) $error . PHP_EOL);
    exit(1);
});
if (!in_array(Env::get('DB_HOST'), ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Deletion regression supports the local database only.');
}
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$pdo = Database::connection();
$children = ['vital_results', 'blood_results', 'urine_results', 'drug_results', 'widal_results',
    'examination_panel_selections', 'examination_parameter_results', 'examination_result_flags', 'print_logs'];
foreach (array_merge(['patients', 'examinations', 'audit_logs', 'lab_number_sequences'], $children) as $table) {
    $definition = (string) $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
    $lines = array_filter(explode("\n", $definition), static fn(string $line): bool => preg_match('/^\s*CONSTRAINT .* FOREIGN KEY/', $line) !== 1);
    $definition = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', implode("\n", $lines));
    $pdo->exec((string) preg_replace('/,(\s*\))/', '$1', $definition));
}
$pdo->exec('INSERT INTO patients (id, medical_record_number, full_name, sex, created_at, updated_at) VALUES (1, "__DELETE_TEST", "Pasien simulasi hapus", "P", UTC_TIMESTAMP(), UTC_TIMESTAMP())');
foreach ([1 => '2610210002', 2 => '2610210003'] as $id => $lab) {
    $pdo->prepare('INSERT INTO examinations (id, patient_id, lab_number, examination_type, status, examined_on, created_by, updated_by, created_at, updated_at) VALUES (?, 1, ?, "mcu_general", "draft", "2026-10-21", 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$id, $lab]);
    foreach (['vital_results', 'blood_results', 'urine_results', 'drug_results', 'widal_results'] as $table) {
        $pdo->prepare('INSERT INTO ' . $table . ' (examination_id, created_at, updated_at) VALUES (?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$id]);
    }
    $pdo->prepare('INSERT INTO examination_panel_selections (examination_id, panel_id, created_at) VALUES (?, 1, UTC_TIMESTAMP())')->execute([$id]);
    $pdo->prepare('INSERT INTO examination_parameter_results (examination_id, parameter_id, parameter_code_snapshot, parameter_label_snapshot, value_text, created_at, updated_at) VALUES (?, 1, "WIDAL_O", "Salmonella typhi O", "Negatif", UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$id]);
    $pdo->prepare('INSERT INTO examination_result_flags (examination_id, test_code, display_name, section, raw_value, interpretation, created_at) VALUES (?, "HGB", "Hemoglobin", "blood", "12", "normal", UTC_TIMESTAMP())')->execute([$id]);
    $pdo->prepare('INSERT INTO print_logs (examination_id, printed_by, document_type, printed_at) VALUES (?, 1, "blood_general", UTC_TIMESTAMP())')->execute([$id]);
}
$pdo->exec('INSERT INTO lab_number_sequences (examined_on, next_sequence, created_at, updated_at) VALUES ("2026-10-21", 4, UTC_TIMESTAMP(), UTC_TIMESTAMP())');
$_SESSION['user'] = ['id' => 1, 'role' => $case === 'forbidden' ? 'perawat' : 'admin', 'full_name' => 'Penguji', 'username' => '__DELETE_TEST'];
$_SERVER['REQUEST_METHOD'] = $case === 'get' ? 'GET' : 'POST';
$_SERVER['REQUEST_URI'] = '/examinations/' . ($case === 'missing' ? '999' : '1') . '/delete';
$_POST = ['confirm_lab_number' => $case === 'wrong_lab' ? 'SALAH' : '2610210002', '_token' => $case === 'csrf' ? 'invalid' : Csrf::token()];
$level = ob_get_level();
ob_start();
register_shutdown_function(static function () use ($pdo, $case, $assert, $children, $level): void {
    while (ob_get_level() > $level) ob_end_clean();
    try {
        $expectedStatus = ['success' => 303, 'wrong_lab' => 303, 'forbidden' => 403, 'csrf' => 419, 'get' => 404, 'missing' => 303][$case];
        $assert(http_response_code() === $expectedStatus, 'Unexpected response for ' . $case . ': ' . http_response_code());
        $deleted = $case === 'success';
        $assert((int) $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn() === 1, 'Patient identity was deleted.');
        $assert((int) $pdo->query('SELECT COUNT(*) FROM examinations WHERE id = 1')->fetchColumn() === ($deleted ? 0 : 1), 'Target deletion mismatch.');
        $assert((int) $pdo->query('SELECT COUNT(*) FROM examinations WHERE id = 2')->fetchColumn() === 1, 'Another examination was deleted.');
        foreach ($children as $table) {
            $assert((int) $pdo->query('SELECT COUNT(*) FROM ' . $table . ' WHERE examination_id = 1')->fetchColumn() === ($deleted ? 0 : 1), 'Target dependent mismatch: ' . $table);
            $assert((int) $pdo->query('SELECT COUNT(*) FROM ' . $table . ' WHERE examination_id = 2')->fetchColumn() === 1, 'Another examination dependent was deleted: ' . $table);
        }
        $assert((int) $pdo->query('SELECT COUNT(*) FROM audit_logs WHERE event = "examination.deleted_permanently"')->fetchColumn() === ($deleted ? 1 : 0), 'Deletion audit mismatch.');
        if ($case === 'success') $assert(($_SESSION['_flash']['type'] ?? '') === 'success', 'Success feedback missing.');
        if ($case === 'wrong_lab') $assert(str_contains($_SESSION['_flash']['message'] ?? '', 'Konfirmasi nomor lab'), 'Confirmation error missing.');
        if ($case === 'missing') $assert(($_SESSION['_flash']['type'] ?? '') === 'error', 'Missing examination feedback missing.');
        $assert(!$pdo->inTransaction(), 'Request left an open transaction.');
        echo '  Delete request ' . $case . ": PASS\n";
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL);
        exit(1);
    }
});
// Bootstrap has run to create isolated tables and a synthetic authenticated session.
// Execute the actual front controller, skipping only its duplicate bootstrap require.
$index = is_file($root . '/public_html/index.php') ? $root . '/public_html/index.php' : $root . '/public/index.php';
$source = file_get_contents($index);
$source = str_replace("require dirname(__DIR__) . '/app/bootstrap.php';", '', $source, $count);
$assert($count === 1, 'Unexpected bootstrap entry in front controller.');
eval(substr($source, strlen('<?php')));
