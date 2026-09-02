<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

if ($method === 'GET' && $path === '/') {
    if (Auth::check()) {
        Auth::redirect('/dashboard');
    }
    View::render('auth/login', ['pageTitle' => 'Masuk']);
    exit;
}

if ($method === 'GET' && $path === '/login') {
    if (Auth::check()) {
        Auth::redirect('/dashboard');
    }
    View::render('auth/login', ['pageTitle' => 'Masuk']);
    exit;
}

if ($method === 'POST' && $path === '/login') {
    if (!Csrf::verify($_POST['_token'] ?? null)) {
        Flash::set('error', 'Permintaan tidak valid. Silakan coba lagi.');
        Auth::redirect('/');
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        Flash::set('error', 'Username dan password wajib diisi.');
        Auth::redirect('/');
    }

    if (!Auth::attempt($username, $password)) {
        Flash::set('error', 'Username atau password tidak tepat.');
        Auth::redirect('/');
    }

    Flash::set('success', 'Selamat datang.');
    Auth::redirect('/dashboard');
}

if ($method === 'POST' && $path === '/logout') {
    if (!Csrf::verify($_POST['_token'] ?? null)) {
        http_response_code(419);
        echo 'Permintaan tidak valid.';
        exit;
    }
    Auth::logout();
    session_start();
    Flash::set('success', 'Anda telah keluar.');
    Auth::redirect('/');
}

if (preg_match('#^/verification/examinations/(\d+)$#', $path, $matches) === 1 && $method === 'GET') {
    $id = (int) $matches[1];
    $token = (string) ($_GET['token'] ?? '');
    if (!PublicVerification::isValid($id, $token)) {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Verifikasi tidak ditemukan']);
        exit;
    }

    $examination = ExaminationRepository::find($id);
    if ($examination === false
        || $examination['examination_type'] !== 'drug_screen'
        || !in_array($examination['status'], ['completed', 'finalized'], true)) {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Verifikasi tidak ditemukan']);
        exit;
    }

    View::render('verification/drug', [
        'pageTitle' => 'Verifikasi hasil skrining narkoba',
        'examination' => $examination,
        'flags' => FinalizationService::flags($id, $examination['status'] === 'finalized'),
        'pageClass' => 'verification-container',
    ]);
    exit;
}

if ($method === 'GET' && $path === '/dashboard') {
    Auth::requireLogin();
    View::render('dashboard', ['pageTitle' => 'Beranda']);
    exit;
}

if ($method === 'GET' && $path === '/patients') {
    Auth::requireRole(['admin', 'perawat']);
    $query = trim((string) ($_GET['q'] ?? ''));
    View::render('patients/index', [
        'pageTitle' => 'Cari pasien',
        'query' => $query,
        'searched' => $query !== '',
        'patients' => $query === '' ? [] : PatientRepository::search($query),
    ]);
    exit;
}

if ($method === 'GET' && $path === '/patients/archived') {
    Auth::requireRole(['admin']);
    $query = trim((string) ($_GET['q'] ?? ''));
    View::render('patients/archived', [
        'pageTitle' => 'Arsip pasien',
        'query' => $query,
        'patients' => PatientRepository::archived($query),
    ]);
    exit;
}

if ($method === 'GET' && $path === '/patients/suggestions') {
    Auth::requireRole(['admin', 'perawat']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(PatientRepository::suggestions((string) ($_GET['q'] ?? '')), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (preg_match('#^/patients/(\d+)/cumulative-results$#', $path, $matches) === 1 && $method === 'GET') {
    Auth::requireRole(['admin', 'perawat']);
    renderPatientCumulativeResults((int) $matches[1]);
}

if (preg_match('#^/patients/(\d+)$#', $path, $matches) === 1 && $method === 'GET') {
    Auth::requireRole(['admin', 'perawat']);
    renderPatient((int) $matches[1]);
}

if ($method === 'GET' && $path === '/patients/create') {
    Auth::requireRole(['admin', 'perawat']);
    View::render('patients/create', [
        'pageTitle' => 'Pasien baru',
        'input' => [],
        'errors' => [],
    ]);
    exit;
}

if ($method === 'POST' && $path === '/patients') {
    Auth::requireRole(['admin', 'perawat']);
    ensureCsrf();
    try {
        $id = PatientRepository::create($_POST, (int) Auth::user()['id']);
        Flash::set('success', 'Pasien berhasil didaftarkan. Buat pemeriksaan baru untuk melanjutkan.');
        Auth::redirect('/examinations/create?patient=' . $id);
    } catch (ValidationException $exception) {
        View::render('patients/create', [
            'pageTitle' => 'Pasien baru',
            'input' => $_POST,
            'errors' => ['form' => firstValidationError($exception)],
        ]);
        exit;
    }
}

if (preg_match('#^/patients/(\d+)/edit$#', $path, $matches) === 1 && $method === 'GET') {
    Auth::requireRole(['admin', 'perawat']);
    renderPatientEdit((int) $matches[1]);
}

if (preg_match('#^/patients/(\d+)/edit$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin', 'perawat']);
    ensureCsrf();
    $id = (int) $matches[1];
    $patient = PatientRepository::find($id);
    if ($patient === false) {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Pasien tidak ditemukan']);
        exit;
    }
    try {
        PatientRepository::update($id, $_POST, (int) Auth::user()['id']);
        Flash::set('success', 'Identitas pasien berhasil diperbarui.');
        Auth::redirect('/patients/' . $id);
    } catch (ValidationException $exception) {
        renderPatientEdit($id, $_POST, ['form' => firstValidationError($exception)]);
    }
}

if (preg_match('#^/patients/(\d+)/archive$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin']);
    ensureCsrf();
    $id = (int) $matches[1];
    try {
        PatientRepository::archive($id, (string) ($_POST['confirm_medical_record_number'] ?? ''), (int) Auth::user()['id']);
        Flash::set('success', 'Pasien berhasil diarsipkan. Riwayat pemeriksaan tetap tersimpan.');
    } catch (ValidationException $exception) {
        Flash::set('error', firstValidationError($exception));
    }
    Auth::redirect('/patients/' . $id);
}

if (preg_match('#^/patients/(\d+)/restore$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin']);
    ensureCsrf();
    $id = (int) $matches[1];
    try {
        PatientRepository::restore($id, (int) Auth::user()['id']);
        Flash::set('success', 'Pasien berhasil dipulihkan dan kembali ke pencarian aktif.');
    } catch (ValidationException $exception) {
        Flash::set('error', firstValidationError($exception));
    }
    Auth::redirect('/patients/' . $id);
}

if (preg_match('#^/patients/(\d+)/delete$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin']);
    ensureCsrf();
    $id = (int) $matches[1];
    try {
        PatientRepository::deletePermanently($id, (string) ($_POST['confirm_medical_record_number'] ?? ''), (int) Auth::user()['id']);
        Flash::set('success', 'Data pasien tanpa riwayat pemeriksaan telah dihapus permanen.');
        Auth::redirect('/patients');
    } catch (ValidationException $exception) {
        Flash::set('error', firstValidationError($exception));
        Auth::redirect('/patients/' . $id);
    }
}

if ($method === 'GET' && $path === '/examinations/create') {
    Auth::requireRole(['admin', 'perawat']);
    $patientId = positiveId($_GET['patient'] ?? null);
    $patient = $patientId === null ? false : PatientRepository::find($patientId);
    if ($patient === false) {
        Flash::set('error', 'Pilih pasien terlebih dahulu.');
        Auth::redirect('/patients');
    }
    if ($patient['archived_at'] !== null) {
        Flash::set('error', 'Pasien telah diarsipkan dan tidak dapat dibuatkan pemeriksaan baru.');
        Auth::redirect('/patients/' . $patientId);
    }
    $wibNow = ExaminationRepository::nowInWib();
    $examinedOn = $wibNow->format('Y-m-d');
    $suggestedLabNumber = ExaminationRepository::suggestLabNumber($examinedOn);
    View::render('examinations/create', [
        'pageTitle' => 'Pemeriksaan baru',
        'patient' => $patient,
        'panels' => ExaminationPanelCatalogRepository::all(),
        'input' => [
            'examined_on' => $examinedOn,
            'examined_time' => $wibNow->format('H:i'),
            'lab_number' => $suggestedLabNumber,
            'suggested_lab_number' => $suggestedLabNumber,
        ],
        'labNumberIsAutomatic' => true,
        'errors' => [],
    ]);
    exit;
}

if ($method === 'GET' && $path === '/examinations/lab-number') {
    Auth::requireRole(['admin', 'perawat']);
    $examinedOn = trim((string) ($_GET['examined_on'] ?? ''));
    try {
        $labNumber = ExaminationRepository::suggestLabNumber($examinedOn);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['lab_number' => $labNumber], JSON_THROW_ON_ERROR);
    } catch (ValidationException $exception) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => firstValidationError($exception)], JSON_THROW_ON_ERROR);
    }
    exit;
}

if ($method === 'POST' && $path === '/examinations') {
    Auth::requireRole(['admin', 'perawat']);
    ensureCsrf();
    $patientId = positiveId($_POST['patient_id'] ?? null);
    $patient = $patientId === null ? false : PatientRepository::find($patientId);
    if ($patient === false) {
        Flash::set('error', 'Pasien tidak ditemukan.');
        Auth::redirect('/patients');
    }
    if ($patient['archived_at'] !== null) {
        Flash::set('error', 'Pasien telah diarsipkan dan tidak dapat dibuatkan pemeriksaan baru.');
        Auth::redirect('/patients/' . $patientId);
    }
    try {
        $id = ExaminationRepository::create($_POST, (int) Auth::user()['id']);
        Flash::set('success', 'Pemeriksaan siap diisi.');
        Auth::redirect('/examinations/' . $id);
    } catch (ValidationException $exception) {
        $_POST['examined_time'] = ExaminationRepository::nowInWib()->format('H:i');
        View::render('examinations/create', [
            'pageTitle' => 'Pemeriksaan baru',
            'patient' => $patient,
            'panels' => ExaminationPanelCatalogRepository::all(),
            'input' => $_POST,
            'labNumberIsAutomatic' => false,
            'errors' => ['form' => firstValidationError($exception)],
        ]);
        exit;
    }
}

if (preg_match('#^/examinations/(\d+)$#', $path, $matches) === 1 && $method === 'GET') {
    Auth::requireLogin();
    renderExamination((int) $matches[1]);
}

if (preg_match('#^/examinations/(\d+)/results$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin', 'perawat']);
    ensureCsrf();
    $id = (int) $matches[1];
    try {
        ExaminationRepository::saveResults($id, $_POST, (int) Auth::user()['id']);
        FinalizationService::saveImmediately($id, (int) Auth::user()['id']);
        $savedExamination = ExaminationRepository::find($id);
        Flash::set('success', 'Hasil pemeriksaan berhasil disimpan.');
        Auth::redirect('/patients/' . (int) ($savedExamination['patient_id'] ?? 0));
    } catch (ValidationException $exception) {
        renderExamination($id, array_merge($_POST, ['id' => $id]), ['form' => firstValidationError($exception)]);
    }
}

if (preg_match('#^/examinations/(\d+)/complete$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin', 'perawat']);
    ensureCsrf();
    $id = (int) $matches[1];
    try {
        ExaminationRepository::complete($id, (int) Auth::user()['id']);
        Flash::set('success', 'Pemeriksaan telah ditandai selesai dan dikunci.');
        Auth::redirect('/examinations/' . $id);
    } catch (ValidationException $exception) {
        renderExamination($id, null, ['form' => firstValidationError($exception)]);
    }
}

if ($method === 'GET' && $path === '/settings/reference-ranges') {
    Auth::requireRole(['admin']);
    renderReferenceRanges();
}

if ($method === 'POST' && $path === '/settings/reference-ranges') {
    Auth::requireRole(['admin']);
    ensureCsrf();
    try {
        ReferenceRangeRepository::create($_POST, (int) Auth::user()['id']);
        Flash::set('success', 'Nilai rujukan tersimpan dan langsung aktif untuk menilai hasil baru.');
        Auth::redirect('/settings/reference-ranges');
    } catch (ValidationException $exception) {
        renderReferenceRanges($_POST, ['form' => firstValidationError($exception)]);
    }
}

if (preg_match('#^/settings/reference-ranges/(\d+)/approve$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin']);
    ensureCsrf();
    try {
        ReferenceRangeRepository::approve(
            (int) $matches[1],
            (int) Auth::user()['id'],
            $_POST['clinical_approval'] ?? null
        );
        Flash::set('success', 'Nilai rujukan disetujui dan versi baru telah diaktifkan.');
    } catch (ValidationException $exception) {
        Flash::set('error', firstValidationError($exception));
    }
    Auth::redirect('/settings/reference-ranges');
}

if (preg_match('#^/settings/reference-ranges/(\d+)/reject$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin']);
    ensureCsrf();
    try {
        ReferenceRangeRepository::reject(
            (int) $matches[1],
            (int) Auth::user()['id'],
            $_POST['review_note'] ?? null
        );
        Flash::set('success', 'Draft nilai rujukan ditolak dan tidak diaktifkan.');
    } catch (ValidationException $exception) {
        Flash::set('error', firstValidationError($exception));
    }
    Auth::redirect('/settings/reference-ranges');
}

if ($method === 'GET' && $path === '/settings/users') {
    Auth::requireRole(['admin']);
    renderUsers();
}

if ($method === 'POST' && $path === '/settings/users') {
    Auth::requireRole(['admin']);
    ensureCsrf();
    try {
        UserRepository::create($_POST, (int) Auth::user()['id']);
        Flash::set('success', 'Pengguna berhasil ditambahkan.');
        Auth::redirect('/settings/users');
    } catch (ValidationException $exception) {
        renderUsers(['form' => firstValidationError($exception)]);
    }
}

if (preg_match('#^/settings/users/(\d+)$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin']);
    ensureCsrf();
    try {
        UserRepository::update((int) $matches[1], $_POST, (int) Auth::user()['id']);
        Flash::set('success', 'Pengguna berhasil diperbarui.');
        Auth::redirect('/settings/users');
    } catch (ValidationException $exception) {
        renderUsers(['form' => firstValidationError($exception)]);
    }
}

if (preg_match('#^/settings/users/(\d+)/delete$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin']);
    ensureCsrf();
    try {
        UserRepository::delete((int) $matches[1], (int) Auth::user()['id']);
        Flash::set('success', 'Akun berhasil dihapus.');
        Auth::redirect('/settings/users');
    } catch (ValidationException $exception) {
        renderUsers(['form' => firstValidationError($exception)]);
    }
}

if ($method === 'GET' && $path === '/reports/export') {
    Auth::requireRole(['admin']);
    AuditLogger::record('report.exported', (int) Auth::user()['id'], 'report', null, null, ['filters' => $_GET]);
    ReportRepository::exportXlsx($_GET);
    exit;
}

if ($method === 'GET' && $path === '/reports') {
    Auth::requireRole(['admin', 'perawat']);
    $canViewReportSummary = (Auth::user()['role'] ?? '') === 'admin';
    $filters = [
        'date_from' => (string) ($_GET['date_from'] ?? ''),
        'date_to' => (string) ($_GET['date_to'] ?? ''),
        'type' => (string) ($_GET['type'] ?? ''),
        'insurance_type' => (string) ($_GET['insurance_type'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
        'q' => (string) ($_GET['q'] ?? ''),
    ];
    $summary = ReportRepository::summary($filters);
    $perPage = 15;
    $totalPages = max(1, (int) ceil($summary['total'] / $perPage));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $page = min($page, $totalPages);
    View::render('reports/index', [
        'pageTitle' => 'Rekap pemeriksaan',
        'pageClass' => 'report-container',
        'filters' => $filters,
        'summary' => $summary,
        'records' => ReportRepository::search($filters, $perPage, ($page - 1) * $perPage),
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => $totalPages,
        'canViewReportSummary' => $canViewReportSummary,
    ]);
    exit;
}

if ($method === 'GET' && $path === '/analytics/export') {
    Auth::requireRole(['admin']);
    $format = (string) ($_GET['format'] ?? 'xlsx');
    if (!in_array($format, ['csv', 'xlsx'], true)) {
        http_response_code(400);
        echo 'Format ekspor analisis tidak valid.';
        exit;
    }
    try {
        $analyticsData = AnalyticsRepository::dashboard($_GET);
    } catch (ValidationException) {
        http_response_code(422);
        echo 'Filter analisis tidak valid.';
        exit;
    }
    AuditLogger::record(
        'analytics.exported',
        (int) Auth::user()['id'],
        'analytics',
        null,
        null,
        AnalyticsAggregateExporter::auditContext($analyticsData, $format)
    );
    if ($format === 'csv') {
        AnalyticsAggregateExporter::downloadCsv($analyticsData);
    }
    AnalyticsAggregateExporter::downloadXlsx($analyticsData);
}

if ($method === 'GET' && $path === '/analytics/help') {
    Auth::requireRole(['admin']);
    View::render('analytics/help', [
        'pageTitle' => 'Bantuan Analisis laboratorium',
        'pageClass' => 'analytics-container',
    ]);
    exit;
}

if ($method === 'GET' && $path === '/analytics') {
    Auth::requireRole(['admin']);
    $filterError = null;
    try {
        $analyticsData = AnalyticsRepository::dashboard($_GET);
    } catch (ValidationException $exception) {
        $filterError = firstValidationError($exception);
        $analyticsData = AnalyticsRepository::dashboard([]);
    }
    View::render('analytics/index', [
        'pageTitle' => 'Analisis laboratorium',
        'pageClass' => 'analytics-container',
        'analyticsData' => $analyticsData,
        'filterError' => $filterError,
    ]);
    exit;
}

if (preg_match('#^/examinations/(\d+)/finalize$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin']);
    ensureCsrf();
    $id = (int) $matches[1];
    try {
        FinalizationService::finalize($id, (int) Auth::user()['id']);
        Flash::set('success', 'Hasil pemeriksaan berhasil disimpan.');
        $savedExamination = ExaminationRepository::find($id);
        Auth::redirect('/patients/' . (int) ($savedExamination['patient_id'] ?? 0));
    } catch (ValidationException $exception) {
        renderExamination($id, null, ['form' => firstValidationError($exception)]);
    }
}

if (preg_match('#^/examinations/(\d+)/preview$#', $path, $matches) === 1 && $method === 'GET') {
    Auth::requireLogin();
    $id = (int) $matches[1];
    $documentType = validDocumentType($_GET['type'] ?? null);
    if ($documentType === null) {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Jenis dokumen tidak ditemukan']);
        exit;
    }
    renderPreview($id, $documentType, false);
}

if (preg_match('#^/examinations/(\d+)/panel-print$#', $path, $matches) === 1 && $method === 'GET') {
    Auth::requireLogin();
    renderPanelPrint((int) $matches[1], false);
}

if (preg_match('#^/examinations/(\d+)/panel-print$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin', 'perawat']);
    ensureCsrf();
    $id = (int) $matches[1];
    try {
        FinalizationService::recordPrint($id, 'panel_results', (int) Auth::user()['id']);
        renderPanelPrint($id, true);
    } catch (ValidationException $exception) {
        Flash::set('error', firstValidationError($exception));
        Auth::redirect('/examinations/' . $id);
    }
}

if (preg_match('#^/examinations/(\d+)/dl-dll$#', $path, $matches) === 1 && $method === 'GET') {
    Auth::requireLogin();
    renderDlDll((int) $matches[1], false);
}

if (preg_match('#^/examinations/(\d+)/dl-dll/print$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin', 'perawat']);
    ensureCsrf();
    $id = (int) $matches[1];
    try {
        FinalizationService::recordPrint($id, 'blood_general', (int) Auth::user()['id']);
        renderDlDll($id, true);
    } catch (ValidationException $exception) {
        Flash::set('error', firstValidationError($exception));
        Auth::redirect('/patients');
    }
}

if (preg_match('#^/examinations/(\d+)/print$#', $path, $matches) === 1 && $method === 'POST') {
    Auth::requireRole(['admin', 'perawat']);
    ensureCsrf();
    $id = (int) $matches[1];
    $documentType = validDocumentType($_POST['document_type'] ?? null);
    if ($documentType === null) {
        Flash::set('error', 'Jenis dokumen tidak valid.');
        Auth::redirect('/examinations/' . $id);
    }
    try {
        FinalizationService::recordPrint($id, $documentType, (int) Auth::user()['id']);
        renderPreview($id, $documentType, true);
    } catch (ValidationException $exception) {
        Flash::set('error', firstValidationError($exception));
        Auth::redirect('/examinations/' . $id);
    }
}

http_response_code(404);
View::render('errors/404', ['pageTitle' => 'Halaman tidak ditemukan']);

function ensureCsrf(): void
{
    if (Csrf::verify($_POST['_token'] ?? null)) {
        return;
    }

    http_response_code(419);
    echo 'Permintaan tidak valid. Silakan kembali dan coba lagi.';
    exit;
}

function positiveId(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : (int) $id;
}

function firstValidationError(ValidationException $exception): string
{
    $errors = $exception->errors();

    return reset($errors) ?: 'Data belum valid.';
}

/** @param array<string, mixed>|null $input @param array<string, string> $errors */
function renderExamination(int $id, ?array $input = null, array $errors = []): never
{
    $examination = ExaminationRepository::find($id);
    if ($examination === false) {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Pemeriksaan tidak ditemukan']);
        exit;
    }
    if ($input !== null) {
        foreach ($input as $key => $value) {
            if (array_key_exists($key, $examination)) {
                $examination[$key] = $value;
            }
        }
    }

    $panelForm = $examination['examination_type'] === 'mcu_general'
        ? ExaminationPanelCatalogRepository::formForExamination($id)
        : [];
    $submittedPanelValues = is_array($input['parameter_values'] ?? null) ? $input['parameter_values'] : [];
    foreach ($panelForm as &$panel) {
        foreach ($panel['parameters'] as &$parameter) {
            $code = (string) $parameter['code'];
            if (array_key_exists($code, $submittedPanelValues) && !is_array($submittedPanelValues[$code])) {
                $parameter['value'] = (string) $submittedPanelValues[$code];
                continue;
            }
            $legacyField = $parameter['legacy_field'];
            if ($parameter['value'] === null && $legacyField !== null && isset($examination[$legacyField])) {
                $parameter['value'] = (string) $examination[$legacyField];
            }
        }
        unset($parameter);
    }
    unset($panel);

    View::render('examinations/show', [
        'pageTitle' => 'Pemeriksaan MCU',
        'examination' => $examination,
        'selectedPanels' => ExaminationPanelCatalogRepository::forExamination($id),
        'panelForm' => $panelForm,
        'errors' => $errors,
    ]);
    exit;
}

function renderPatient(int $id): never
{
    $patient = PatientRepository::find($id);
    if ($patient === false) {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Pasien tidak ditemukan']);
        exit;
    }

    View::render('patients/show', [
        'pageTitle' => 'Data pasien',
        'patient' => $patient,
        'examinations' => PatientRepository::examinations($id),
        'canManagePatientRecord' => (Auth::user()['role'] ?? '') === 'admin',
    ]);
    exit;
}

function renderPatientCumulativeResults(int $id): never
{
    $patient = PatientRepository::find($id);
    if ($patient === false) {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Pasien tidak ditemukan']);
        exit;
    }

    $filters = CumulativeResultRepository::normalizeFilters([
        'from' => $_GET['from'] ?? '',
        'to' => $_GET['to'] ?? '',
        'category' => $_GET['category'] ?? '',
        'limit' => $_GET['limit'] ?? '5',
    ]);

    View::render('patients/cumulative_results', [
        'pageTitle' => 'Hasil pemeriksaan kumulatif',
        'patient' => $patient,
        'filters' => $filters,
        'cumulativeData' => CumulativeResultRepository::forPatient($id, null, $filters),
    ]);
    exit;
}

/** @param array<string, mixed> $input @param array<string, string> $errors */
function renderPatientEdit(int $id, array $input = [], array $errors = []): never
{
    $patient = PatientRepository::find($id);
    if ($patient === false) {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Pasien tidak ditemukan']);
        exit;
    }
    if ($patient['archived_at'] !== null) {
        Flash::set('error', 'Pasien telah diarsipkan. Pulihkan data pasien terlebih dahulu untuk mengubah identitasnya.');
        Auth::redirect('/patients/' . $id);
    }

    View::render('patients/edit', [
        'pageTitle' => 'Edit identitas pasien',
        'patient' => $patient,
        'input' => $input,
        'errors' => $errors,
    ]);
    exit;
}

/** @param array<string, mixed> $input @param array<string, string> $errors */
function renderReferenceRanges(array $input = [], array $errors = []): never
{
    View::render('settings/reference_ranges', [
        'pageTitle' => 'Konfigurasi nilai rujukan',
        'catalog' => ReferenceRangeRepository::catalog(),
        'ranges' => ReferenceRangeRepository::all(),
        'input' => $input,
        'errors' => $errors,
    ]);
    exit;
}

/** @param array<string, string> $errors */
function renderUsers(array $errors = []): never
{
    View::render('settings/users', [
        'pageTitle' => 'Pengguna',
        'users' => UserRepository::all(),
        'errors' => $errors,
        'currentUserId' => (int) (Auth::user()['id'] ?? 0),
    ]);
    exit;
}

function validDocumentType(mixed $value): ?string
{
    $value = (string) $value;

    return in_array($value, ['blood_general', 'urine_complete', 'newborn_blood', 'drug_screen'], true) ? $value : null;
}

function renderPreview(int $id, string $documentType, bool $autoPrint): never
{
    $examination = ExaminationRepository::find($id);
    if ($examination === false) {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Pemeriksaan tidak ditemukan']);
        exit;
    }
    if (!in_array($examination['status'], ['draft', 'completed', 'finalized'], true)) {
        Flash::set('error', 'Selesaikan input pemeriksaan sebelum membuka preview hasil.');
        Auth::redirect('/examinations/' . $id);
    }
    if (!in_array($documentType, ExaminationType::documentTypes((string) $examination['examination_type']), true)) {
        Flash::set('error', 'Jenis preview tidak sesuai dengan pemeriksaan.');
        Auth::redirect('/examinations/' . $id);
    }

    View::render('examinations/preview', [
        'pageTitle' => 'Preview hasil',
        'examination' => $examination,
        'documentType' => $documentType,
        'flags' => FinalizationService::flags($id, $examination['status'] === 'finalized'),
        'autoPrint' => $autoPrint,
    ]);
    exit;
}

function renderDlDll(int $id, bool $autoPrint): never
{
    $examination = ExaminationRepository::find($id);
    if ($examination === false) {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Pemeriksaan tidak ditemukan']);
        exit;
    }
    if ($examination['examination_type'] !== 'mcu_general') {
        Flash::set('error', 'Tampilan DL/DLL hanya tersedia untuk pemeriksaan darah lengkap.');
        Auth::redirect('/examinations/' . $id);
    }

    $panelForm = ExaminationPanelCatalogRepository::formForExamination($id);
    foreach ($panelForm as &$panel) {
        foreach ($panel['parameters'] as &$parameter) {
            $legacyField = $parameter['legacy_field'];
            if ($parameter['value'] === null && $legacyField !== null && isset($examination[$legacyField])) {
                $parameter['value'] = (string) $examination[$legacyField];
            }
        }
        unset($parameter);
    }
    unset($panel);

    View::render('examinations/dl_dll', [
        'pageTitle' => 'Hasil DL/DLL',
        'examination' => $examination,
        'flags' => FinalizationService::flags($id, $examination['status'] === 'finalized'),
        'panelForm' => $panelForm,
        'autoPrint' => $autoPrint,
    ]);
    exit;
}

function renderPanelPrint(int $id, bool $autoPrint): never
{
    $examination = ExaminationRepository::find($id);
    if ($examination === false) {
        http_response_code(404);
        View::render('errors/404', ['pageTitle' => 'Pemeriksaan tidak ditemukan']);
        exit;
    }

    $panelForm = $examination['examination_type'] === 'mcu_general'
        ? ExaminationPanelCatalogRepository::formForExamination($id)
        : [];
    if ($panelForm === []) {
        Flash::set('error', 'Cetak berbasis panel belum tersedia untuk pemeriksaan ini.');
        Auth::redirect('/examinations/' . $id);
    }

    foreach ($panelForm as &$panel) {
        foreach ($panel['parameters'] as &$parameter) {
            $legacyField = $parameter['legacy_field'];
            if ($parameter['value'] === null && $legacyField !== null && isset($examination[$legacyField])) {
                $parameter['value'] = (string) $examination[$legacyField];
            }
        }
        unset($parameter);
    }
    unset($panel);

    View::render('examinations/panel_print', [
        'pageTitle' => 'Preview hasil panel',
        'examination' => $examination,
        'panelForm' => $panelForm,
        'flags' => FinalizationService::flags($id, $examination['status'] === 'finalized'),
        'autoPrint' => $autoPrint,
    ]);
    exit;
}
