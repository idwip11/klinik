<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string) $error . PHP_EOL);
    exit(1);
});

function reportingAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = Database::connection();
$prefix = '__PANEL_REPORT_' . bin2hex(random_bytes(5));
$userId = (int) $pdo->query('SELECT id FROM users WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
reportingAssert($userId > 0, 'Pengguna aktif diperlukan untuk pengujian.');
$pdo->beginTransaction();
register_shutdown_function(static function () use ($pdo): void {
    if ($pdo->inTransaction()) $pdo->rollBack();
});
try {
    $pdo->prepare('INSERT INTO patients (medical_record_number, full_name, sex, birth_date, insurance_type, created_at, updated_at) VALUES (?, "Pasien sintetis pelaporan", "P", "2070-01-01", "Umum", UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$prefix]);
    $patientId = (int) $pdo->lastInsertId();
    $createExamination = static function (string $suffix) use ($pdo, $patientId, $prefix, $userId): int {
        $pdo->prepare('INSERT INTO examinations (patient_id, lab_number, examination_type, status, examined_on, created_by, updated_by, created_at, updated_at) VALUES (?, ?, "mcu_general", "completed", "2099-12-31", ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$patientId, $prefix . $suffix, $userId, $userId]);
        return (int) $pdo->lastInsertId();
    };
    $ancId = $createExamination('_ANC');
    $rapidId = $createExamination('_RAPID');
    $legacyId = $createExamination('_LEGACY');
    $ancSelection = ExaminationPanelCatalogRepository::selectionFromInput(['anc_ibu_hamil'], $pdo, 'P');
    ExaminationPanelCatalogRepository::storeSelections($pdo, $ancId, $ancSelection);
    ExaminationPanelCatalogRepository::storeSelections($pdo, $rapidId, ExaminationPanelCatalogRepository::selectionFromInput(['immunoserology'], $pdo, 'P'));
    $ancValues = ['ANC_HGB' => '11,8', 'ANC_BLOOD_GROUP' => 'B / Rh (+)', 'ANC_HBSAG_RAPID' => 'Non Reaktif', 'ANC_HIV_RAPID' => 'Non Reaktif', 'ANC_SYPHILIS_RAPID' => 'Negatif', 'ANC_GDS' => '120', 'ANC_URINE_ALBUMIN' => 'Negatif', 'ANC_URINE_REDUCTION' => 'Negatif'];
    $ancNormalized = ExaminationParameterResultRepository::normalize(ExaminationPanelCatalogRepository::formForExamination($ancId), $ancValues);
    ExaminationParameterResultRepository::replace($pdo, $ancId, $ancNormalized['rows']);
    $rapidNormalized = ExaminationParameterResultRepository::normalize(ExaminationPanelCatalogRepository::formForExamination($rapidId), ['HBSAG_RAPID' => 'Reaktif', 'HIV_RAPID' => 'Non Reaktif', 'SYPHILIS_RAPID' => 'Positif']);
    ExaminationParameterResultRepository::replace($pdo, $rapidId, $rapidNormalized['rows']);
    $pdo->prepare('INSERT INTO widal_results (examination_id, widal_ao, created_at, updated_at) VALUES (?, "(+) 1/160", UTC_TIMESTAMP(), UTC_TIMESTAMP())')->execute([$legacyId]);

    $filters = ['q' => $prefix, 'panel' => 'anc_ibu_hamil', 'sex' => 'P'];
    $records = ReportRepository::search($filters);
    reportingAssert(count($records) === 1 && (int) $records[0]['id'] === $ancId, 'Filter ANC Rekap tidak konsisten.');
    reportingAssert(ReportRepository::summary($filters)['total'] === 1, 'Ringkasan Rekap tidak mengikuti filter ANC.');
    reportingAssert(ReportRepository::summary(array_merge($filters, ['sex' => 'L']))['total'] === 0, 'Filter jenis kelamin Rekap tidak diterapkan.');
    $record = $records[0];
    $summary = ReportRepository::summary($filters);
    $reportPanels = ExaminationPanelCatalogRepository::all();
    $canViewReportSummary = true;
    $page = 1;
    $perPage = 15;
    $totalPages = 1;
    ob_start();
    require ROOT_PATH . '/app/views/reports/index.php';
    $reportHtml = (string) ob_get_clean();
    reportingAssert(str_contains($reportHtml, 'name="panel"') && str_contains($reportHtml, 'value="anc_ibu_hamil" selected'), 'Form filter Rekap tidak menampilkan pilihan ANC.');
    reportingAssert(str_contains($reportHtml, '8 hasil') && str_contains($reportHtml, '11,8') && str_contains($reportHtml, 'Golongan Darah / Rhesus'), 'Tampilan Rekap tidak memuat rincian ANC.');
    reportingAssert(count($record['panel_results']) === 8, 'Rekap harus memuat delapan hasil ANC.');
    reportingAssert((float) $record['hgb'] === 11.8 && $record['blood_group'] === 'B' && $record['rhesus'] === '+', 'Kolom ringkasan Rekap tidak menampilkan hasil ANC.');
    reportingAssert((float) $record['gds'] === 120.0, 'Kolom GDS Rekap tidak menampilkan Gula Darah Sewaktu ANC.');
    $reported = array_column($record['panel_results'], null, 'code');
    reportingAssert($reported['ANC_HGB']['reference_text'] === '11,4 – 15,1', 'Rincian Rekap tidak memakai rujukan ANC.');
    reportingAssert($reported['ANC_BLOOD_GROUP']['unassessed_reason'] === 'not_applicable', 'Golongan darah ANC dianggap kekurangan rujukan.');
    $widalRecords = ReportRepository::search(['q' => $prefix, 'panel' => 'widal']);
    reportingAssert(count($widalRecords) === 3, 'Filter Widal Rekap harus mencakup pilihan baru dan hasil legacy.');
    $legacy = array_values(array_filter($widalRecords, static fn (array $row): bool => (int) $row['id'] === $legacyId))[0];
    reportingAssert($legacy['panel_results'][0]['value'] === '(+) 1/160', 'Titer Widal legacy hilang atau berubah pada Rekap.');
    $rapid = ReportRepository::search(['q' => $prefix, 'panel' => 'immunoserology']);
    reportingAssert(count($rapid) === 1 && count($rapid[0]['panel_results']) === 3, 'Rekap Imunoserologi tidak memuat hasil rapid.');
    reportingAssert(ReportRepository::search($filters, 1, 1) === [], 'Pagination Rekap tidak mengikuti filter.');

    $analysisFilters = ['date_from' => '2099-12-31', 'date_to' => '2099-12-31', 'panel' => 'anc_ibu_hamil', 'sex' => 'P', 'status' => 'completed'];
    $dashboard = AnalyticsRepository::dashboard($analysisFilters, $pdo);
    reportingAssert($dashboard['summary']['total_examinations'] === 1, 'Analisis tidak mengenali panel ANC.');
    $totals = $dashboard['clinical']['totals'];
    reportingAssert($totals['total_results'] === 8 && $totals['assessed_results'] === 7 && $totals['normal_results'] === 7, 'Analisis ANC harus memuat tujuh hasil dengan rujukan dan satu golongan darah.');
    reportingAssert($totals['not_applicable_results'] === 1 && $totals['not_assessed_results'] === 0 && $totals['parameters_without_reference'] === 0, 'Golongan darah tidak boleh memperburuk kualitas rujukan.');
    reportingAssert(AnalyticsRepository::dashboard(array_merge($analysisFilters, ['sex' => 'L']), $pdo)['summary']['total_examinations'] === 0, 'Analisis ANC tidak mengikuti filter perempuan.');
    $allAnalysis = AnalyticsRepository::dashboard(array_merge($analysisFilters, ['panel' => '']), $pdo);
    reportingAssert($allAnalysis['quality']['incomplete_modern_panels'] === 1, 'Panel Widal otomatis kosong seharusnya tidak dihitung belum lengkap; hanya Imunoserologi parsial yang dihitung.');
    $widalAnalysis = AnalyticsRepository::dashboard(array_merge($analysisFilters, ['panel' => 'widal']), $pdo);
    reportingAssert($widalAnalysis['summary']['total_examinations'] === 1, 'Analisis Widal hanya boleh menghitung pemeriksaan yang sudah memiliki hasil Widal.');

    $history = PatientRepository::examinations($patientId);
    $historyAnc = array_values(array_filter($history, static fn (array $exam): bool => (int) $exam['id'] === $ancId))[0];
    reportingAssert(count($historyAnc['result_summary']) === 8, 'Riwayat pasien kehilangan hasil ANC.');
    $cumulative = CumulativeResultRepository::forPatient($patientId, $pdo, ['category' => 'anc_ibu_hamil', 'limit' => 'all']);
    reportingAssert(count($cumulative['parameters']) === 8, 'Tabel kumulatif tidak menampilkan delapan parameter ANC.');
    reportingAssert($cumulative['results'][$ancId]['ANC_HGB']['reference_text'] === '11,4 – 15,1', 'Rujukan ANC kumulatif tidak konsisten.');
    reportingAssert($cumulative['results'][$ancId]['ANC_BLOOD_GROUP']['reference_text'] === '', 'Golongan darah tidak boleh diberi rujukan pada hasil kumulatif.');

    // Exercise the actual legacy templates as well as repository methods. This
    // catches compatibility failures when the production app is updated in place.
    $previousSession = $_SESSION;
    try {
        $_SESSION['user'] = ['id' => $userId, 'full_name' => 'Penguji sintetis', 'username' => '__overlay', 'role' => 'admin'];
        $render = static function (string $template, array $data): string {
            ob_start();
            try {
                View::render($template, $data);
                return (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
        };
        $patient = PatientRepository::find($patientId);
        $historyHtml = $render('patients/show', [
            'patient' => $patient, 'examinations' => $history,
            'canManagePatientRecord' => true,
        ]);
        reportingAssert(str_contains($historyHtml, $prefix . '_ANC') && str_contains($historyHtml, 'Golongan Darah / Rhesus'), 'Halaman riwayat production gagal menampilkan hasil ANC.');
        $cumulativeHtml = $render('patients/cumulative_results', [
            'patient' => $patient, 'cumulativeData' => $cumulative,
            'filters' => CumulativeResultRepository::normalizeFilters(['category' => 'anc_ibu_hamil', 'limit' => 'all']),
        ]);
        reportingAssert(str_contains($cumulativeHtml, 'Hemoglobin') && str_contains($cumulativeHtml, '11,4 – 15,1'), 'Halaman kumulatif production gagal menampilkan rujukan ANC.');
    } finally {
        $_SESSION = $previousSession;
    }

    $sheet = ReportExcelExporter::panelSheet($records);
    reportingAssert(count($sheet['rows']) === 8 && count($sheet['headers']) === 11, 'Sheet Hasil Panel harus lengkap.');
    $hgbRows = array_values(array_filter($sheet['rows'], static fn (array $row): bool => $row[6] === 'Hemoglobin'));
    reportingAssert($hgbRows[0][7] === 11.8 && $hgbRows[0][9] === '11,4 – 15,1', 'Excel Rekap tidak membedakan hasil ANC dan nilai rujukan.');
    $groupRows = array_values(array_filter($sheet['rows'], static fn (array $row): bool => $row[6] === 'Golongan Darah / Rhesus'));
    reportingAssert($groupRows[0][9] === '' && $groupRows[0][10] === '', 'Excel Rekap tidak boleh menilai golongan darah sebagai abnormal.');
    $analysisSheets = AnalyticsAggregateExporter::sheets($dashboard);
    reportingAssert(in_array('Tanpa penilaian', $analysisSheets[5]['headers'], true), 'Excel Analisis tidak mengikuti kolom baru.');
    $analysisGroup = array_values(array_filter($analysisSheets[5]['rows'], static fn (array $row): bool => $row[1] === 'ANC_BLOOD_GROUP'))[0];
    reportingAssert($analysisGroup[7] === 1 && $analysisGroup[8] === '', 'Excel Analisis tidak boleh menampilkan persentase abnormal untuk golongan darah.');
    foreach (AnalyticsAggregateExporter::rows($dashboard) as $row) {
        reportingAssert(!str_contains(implode(' ', $row), $prefix), 'Ekspor Analisis tidak boleh memuat identitas pasien.');
    }

    if (($argv[1] ?? '') === '--export-reports') ReportRepository::exportXlsx(['q' => $prefix]);
    if (($argv[1] ?? '') === '--export-analysis') AnalyticsAggregateExporter::downloadXlsx($dashboard);
    if (($argv[1] ?? '') === '--export-csv') {
        ReportRepository::exportCsv($filters);
        exit;
    }
} finally {
    $pdo->rollBack();
}
echo "Panel reporting regression: OK; transaksi uji dibatalkan.\n";
