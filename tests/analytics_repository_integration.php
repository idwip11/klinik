<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

function analyticsAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string,mixed> $filters */
function analyticsTotal(array $filters, PDO $pdo): int
{
    return AnalyticsRepository::dashboard(array_merge([
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-31',
    ], $filters), $pdo)['summary']['total_examinations'];
}

function analyticsExpectInvalid(array $filters, PDO $pdo): void
{
    try {
        AnalyticsRepository::dashboard($filters, $pdo);
    } catch (ValidationException) {
        return;
    }

    throw new RuntimeException('Filter tidak valid seharusnya ditolak.');
}

/** @param list<array<string,mixed>> $rows */
function analyticsRowTotal(array $rows, string $code): int
{
    foreach ($rows as $row) {
        if (($row['code'] ?? null) === $code) {
            return (int) ($row['total'] ?? 0);
        }
    }

    return 0;
}

/** @param list<array<string,mixed>> $rows @return array<string,mixed> */
function analyticsFindRow(array $rows, string $code): array
{
    foreach ($rows as $row) {
        if (($row['code'] ?? null) === $code) {
            return $row;
        }
    }

    throw new RuntimeException('Baris analitik tidak ditemukan: ' . $code);
}

/** @param list<list<string|int|float>> $rows @return list<string|int|float> */
function analyticsFindExportRow(array $rows, string $section, string $group, string $metric): array
{
    foreach ($rows as $row) {
        if (($row[0] ?? null) === $section && ($row[1] ?? null) === $group && ($row[2] ?? null) === $metric) {
            return $row;
        }
    }

    throw new RuntimeException('Baris ekspor analisis tidak ditemukan.');
}

/** @param list<list<string|int|float>> $rows @return list<string|int|float> */
function analyticsFindSheetRow(array $rows, string $value, int $column = 0): array
{
    foreach ($rows as $row) {
        if (($row[$column] ?? null) === $value) {
            return $row;
        }
    }

    throw new RuntimeException('Baris sheet ekspor tidak ditemukan: ' . $value);
}

$pdo = Database::connection();

// Tabel sementara membayangi tabel aplikasi hanya pada koneksi pengujian ini.
// Dengan demikian seluruh cabang filter dapat diuji tanpa mengubah data klinik.
$temporaryTables = [
    'CREATE TEMPORARY TABLE patients (
        id BIGINT UNSIGNED PRIMARY KEY,
        insurance_type VARCHAR(80) NULL,
        sex VARCHAR(8) NULL,
        birth_date DATE NULL,
        archived_at DATETIME NULL
    )',
    'CREATE TEMPORARY TABLE examinations (
        id BIGINT UNSIGNED PRIMARY KEY,
        patient_id BIGINT UNSIGNED NOT NULL,
        lab_number VARCHAR(50) NOT NULL,
        examination_type VARCHAR(32) NOT NULL,
        status VARCHAR(20) NOT NULL,
        examined_on DATE NOT NULL,
        examined_time TIME NULL
    )',
    'CREATE TEMPORARY TABLE examination_panels (
        id BIGINT UNSIGNED PRIMARY KEY,
        code VARCHAR(64) NOT NULL,
        name VARCHAR(150) NOT NULL,
        is_active TINYINT NOT NULL,
        display_order SMALLINT UNSIGNED NOT NULL
    )',
    'CREATE TEMPORARY TABLE test_parameters (
        id BIGINT UNSIGNED PRIMARY KEY,
        panel_id BIGINT UNSIGNED NOT NULL,
        code VARCHAR(64) NOT NULL,
        label VARCHAR(150) NOT NULL,
        result_type VARCHAR(20) NOT NULL,
        unit VARCHAR(32) NULL,
        is_active TINYINT NOT NULL,
        display_order SMALLINT UNSIGNED NOT NULL
    )',
    'CREATE TEMPORARY TABLE examination_panel_selections (
        examination_id BIGINT UNSIGNED NOT NULL,
        panel_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (examination_id, panel_id)
    )',
    'CREATE TEMPORARY TABLE examination_parameter_results (
        id BIGINT UNSIGNED PRIMARY KEY,
        examination_id BIGINT UNSIGNED NOT NULL,
        parameter_id BIGINT UNSIGNED NOT NULL,
        parameter_code_snapshot VARCHAR(64) NOT NULL,
        parameter_label_snapshot VARCHAR(150) NOT NULL,
        unit_snapshot VARCHAR(32) NULL,
        value_numeric DECIMAL(20,6) NULL,
        value_text VARCHAR(500) NULL
    )',
    'CREATE TEMPORARY TABLE reference_ranges (
        id BIGINT UNSIGNED PRIMARY KEY,
        test_code VARCHAR(64) NOT NULL,
        result_kind VARCHAR(20) NOT NULL,
        unit VARCHAR(50) NULL,
        sex VARCHAR(8) NOT NULL,
        min_age_days INT NULL,
        max_age_days INT NULL,
        min_age_years INT NULL,
        max_age_years INT NULL,
        lower_bound DECIMAL(18,6) NULL,
        upper_bound DECIMAL(18,6) NULL,
        lower_inclusive TINYINT NOT NULL,
        upper_inclusive TINYINT NOT NULL,
        normal_values LONGTEXT NULL,
        version INT NOT NULL,
        approved_at DATETIME NULL,
        approval_status VARCHAR(20) NOT NULL,
        is_active TINYINT NOT NULL
    )',
    'CREATE TEMPORARY TABLE blood_results (
        examination_id BIGINT UNSIGNED NOT NULL,
        hgb DECIMAL(12,2) NULL, wbc DECIMAL(12,2) NULL, plt DECIMAL(14,2) NULL,
        rbc DECIMAL(12,2) NULL, hct DECIMAL(12,2) NULL, lymph_pct DECIMAL(12,2) NULL,
        mid_pct DECIMAL(12,2) NULL, gran_pct DECIMAL(12,2) NULL, mcv DECIMAL(12,2) NULL,
        mch DECIMAL(12,2) NULL, mchc DECIMAL(12,2) NULL, eosinophil DECIMAL(12,2) NULL,
        basophil DECIMAL(12,2) NULL, neutrophil DECIMAL(12,2) NULL, lymphocyte DECIMAL(12,2) NULL,
        monocyte DECIMAL(12,2) NULL, gds DECIMAL(12,2) NULL, fasting_glucose DECIMAL(12,2) NULL,
        glucose_2h_pp DECIMAL(12,2) NULL, urea DECIMAL(12,2) NULL, creatinine DECIMAL(12,2) NULL,
        cholesterol DECIMAL(12,2) NULL, uric_acid DECIMAL(12,2) NULL, sgot DECIMAL(12,2) NULL,
        sgpt DECIMAL(12,2) NULL
    )',
    'CREATE TEMPORARY TABLE urine_results (
        examination_id BIGINT UNSIGNED NOT NULL,
        color VARCHAR(50) NULL, clarity VARCHAR(50) NULL, specific_gravity DECIMAL(6,3) NULL,
        ph DECIMAL(4,1) NULL, protein VARCHAR(100) NULL, glucose_reduction VARCHAR(100) NULL,
        urobilinogen VARCHAR(100) NULL, bilirubin VARCHAR(100) NULL, occult_blood VARCHAR(100) NULL,
        ketone VARCHAR(100) NULL, nitrite VARCHAR(100) NULL, leukocyte_esterase VARCHAR(100) NULL,
        erythrocytes VARCHAR(100) NULL, leukocytes VARCHAR(100) NULL, squamous_cells VARCHAR(100) NULL,
        casts VARCHAR(100) NULL, crystals VARCHAR(100) NULL, bacteria VARCHAR(100) NULL
    )',
    'CREATE TEMPORARY TABLE widal_results (
        examination_id BIGINT UNSIGNED NOT NULL,
        widal_o VARCHAR(50) NULL, widal_ao VARCHAR(50) NULL,
        widal_bo VARCHAR(50) NULL, widal_h VARCHAR(50) NULL
    )',
    'CREATE TEMPORARY TABLE drug_results (
        examination_id BIGINT UNSIGNED NOT NULL,
        amphetamine VARCHAR(30) NULL, marijuana VARCHAR(30) NULL, morphine VARCHAR(30) NULL,
        benzodiazepine VARCHAR(30) NULL, methamphetamine VARCHAR(30) NULL, cocaine VARCHAR(30) NULL
    )',
    'CREATE TEMPORARY TABLE vital_results (
        examination_id BIGINT UNSIGNED NOT NULL,
        blood_group VARCHAR(3) NULL, rhesus VARCHAR(3) NULL
    )',
];
foreach ($temporaryTables as $sql) {
    $pdo->exec($sql);
}

$pdo->exec("INSERT INTO examination_panels (id, code, name, is_active, display_order) VALUES
    (1, 'hematology_complete', 'Hematologi', 1, 10),
    (2, 'clinical_chemistry', 'Kimia Klinik', 1, 20),
    (3, 'additional_rare', 'Pemeriksaan Tambahan', 1, 30)");
$pdo->exec("INSERT INTO test_parameters (id, panel_id, code, label, result_type, unit, is_active, display_order) VALUES
    (10, 1, 'HGB', 'Hemoglobin', 'numeric', 'g/dL', 1, 10),
    (12, 1, 'WBC', 'Leukosit', 'numeric', 'sel/mm3', 1, 20),
    (11, 3, 'MALARIA_RAPID', 'Malaria Rapid', 'text', NULL, 1, 10)");
$pdo->exec("INSERT INTO patients (id, insurance_type, sex, birth_date, archived_at) VALUES
    (1, 'BPJS Kesehatan', 'L', '2000-01-01', NULL),
    (2, NULL, 'P', '1970-01-01', NULL),
    (3, 'Umum', 'L', '2010-01-01', '2026-08-20 10:00:00'),
    (4, '   ', NULL, NULL, NULL)");
$pdo->exec("INSERT INTO examinations (id, patient_id, lab_number, examination_type, status, examined_on) VALUES
    (101, 1, 'LAB-101', 'mcu_general', 'completed', '2026-08-05'),
    (102, 1, 'LAB-102', 'drug_screen', 'finalized', '2026-08-10'),
    (103, 2, 'LAB-103', 'newborn_blood', 'completed', '2026-08-15'),
    (104, 2, 'LAB-104', 'mcu_general', 'draft', '2026-08-20'),
    (105, 3, 'LAB-105', 'mcu_general', 'completed', '2026-08-08'),
    (106, 4, 'LAB-106', 'mcu_general', 'completed', '2026-07-01'),
    (107, 4, 'LAB-107', 'mcu_general', 'completed', '2026-08-25'),
    (108, 1, 'LAB-108', 'mcu_general', 'completed', '2026-08-28')");
$pdo->exec("INSERT INTO examination_parameter_results
    (id, examination_id, parameter_id, parameter_code_snapshot, parameter_label_snapshot, unit_snapshot, value_numeric, value_text) VALUES
    (1, 101, 10, 'HGB', 'Hemoglobin', 'g/dL', 0, NULL),
    (2, 107, 11, 'MALARIA_RAPID', 'Malaria Rapid', NULL, NULL, 'Reaktif')");
$pdo->exec('INSERT INTO examination_panel_selections (examination_id, panel_id) VALUES (101, 1), (107, 3)');
$pdo->exec('INSERT INTO drug_results (examination_id, amphetamine) VALUES (102, "NEGATIF")');
$pdo->exec('INSERT INTO blood_results (examination_id, hgb, gds) VALUES (101, 15, NULL), (103, NULL, 110), (105, NULL, 125)');
$pdo->exec("INSERT INTO reference_ranges
    (id, test_code, result_kind, unit, sex, min_age_days, max_age_days, min_age_years, max_age_years,
     lower_bound, upper_bound, lower_inclusive, upper_inclusive, normal_values, version, approved_at, approval_status, is_active) VALUES
    (1, 'HGB', 'numeric', 'g/dL', 'ALL', NULL, NULL, NULL, NULL, 10, 20, 1, 1, NULL, 1, '2026-08-01 00:00:00', 'approved', 1),
    (2, 'GDS', 'numeric', 'mg/dL', 'ALL', NULL, NULL, NULL, NULL, 70, 100, 1, 1, NULL, 1, '2026-08-01 00:00:00', 'approved', 1),
    (3, 'DRUG_AMPHETAMINE', 'qualitative', NULL, 'ALL', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '[\"NEGATIF\"]', 1, '2026-08-01 00:00:00', 'approved', 1)");

$base = AnalyticsRepository::dashboard([
    'date_from' => '2026-08-01',
    'date_to' => '2026-08-31',
], $pdo);
analyticsAssert($base['summary']['total_examinations'] === 6, 'Total pemeriksaan dasar tidak sesuai.');
analyticsAssert($base['summary']['unique_patients'] === 3, 'Jumlah pasien unik tidak sesuai.');
analyticsAssert(
    $base['options']['insurance_types'] === array_keys(PatientRepository::INSURANCE_TYPES),
    'Pilihan filter asuransi harus mengikuti daftar penjamin pasien.'
);
analyticsAssert($base['summary']['first_examined_on'] === '2026-08-05', 'Tanggal pemeriksaan pertama tidak sesuai.');
analyticsAssert($base['summary']['last_examined_on'] === '2026-08-28', 'Tanggal pemeriksaan terakhir tidak sesuai.');
analyticsAssert($base['operational']['trend']['grouping'] === 'day', 'Periode satu bulan seharusnya dikelompokkan per hari.');
analyticsAssert(array_sum(array_column($base['operational']['trend']['rows'], 'total_examinations')) === 6, 'Total tren harian tidak konsisten dengan KPI.');
analyticsAssert(analyticsRowTotal($base['operational']['examination_types'], 'mcu_general') === 4, 'Pemakaian jenis pemeriksaan tidak sesuai.');
analyticsAssert(analyticsRowTotal($base['operational']['panels'], 'hematology_complete') === 1, 'Pemakaian panel modern tidak sesuai.');
analyticsAssert(analyticsRowTotal($base['operational']['panels'], 'clinical_chemistry') === 1, 'Pemakaian panel legacy tidak sesuai.');
analyticsAssert(analyticsRowTotal($base['operational']['panels'], 'drug_screen') === 1, 'Pemakaian panel skrining narkoba tidak sesuai.');
analyticsAssert(analyticsRowTotal($base['operational']['insurance'], 'BPJS Kesehatan') === 3, 'Sebaran asuransi tidak sesuai.');
analyticsAssert(analyticsRowTotal($base['operational']['insurance'], AnalyticsRepository::EMPTY_INSURANCE) === 3, 'Kategori asuransi kosong tidak sesuai.');
$bpjsInsurance = analyticsFindRow($base['operational']['insurance'], 'BPJS Kesehatan');
analyticsAssert($bpjsInsurance['unique_patients'] === 1 && $bpjsInsurance['unique_percentage'] === 33.3, 'Jumlah pasien unik BPJS tidak sesuai.');
$emptyInsurance = analyticsFindRow($base['operational']['insurance'], AnalyticsRepository::EMPTY_INSURANCE);
analyticsAssert($emptyInsurance['unique_patients'] === 2 && $emptyInsurance['unique_percentage'] === 66.7, 'Jumlah pasien unik tanpa penjamin tidak sesuai.');
analyticsAssert(analyticsRowTotal($base['operational']['demographics']['age_groups'], '18_39') === 1, 'Demografi usia dewasa muda tidak sesuai.');
analyticsAssert(analyticsRowTotal($base['operational']['demographics']['age_groups'], '40_59') === 1, 'Demografi usia dewasa tidak sesuai.');
analyticsAssert(analyticsRowTotal($base['operational']['demographics']['age_groups'], 'unknown') === 1, 'Demografi usia tidak diketahui tidak sesuai.');
analyticsAssert(analyticsRowTotal($base['operational']['demographics']['sexes'], 'L') === 1, 'Demografi laki-laki tidak sesuai.');
analyticsAssert(analyticsRowTotal($base['operational']['demographics']['sexes'], 'P') === 1, 'Demografi perempuan tidak sesuai.');
analyticsAssert(analyticsRowTotal($base['operational']['demographics']['sexes'], 'unknown') === 1, 'Demografi jenis kelamin tidak diketahui tidak sesuai.');
analyticsAssert($base['summary']['abnormal_results'] === 2, 'KPI hasil abnormal tidak sesuai.');
analyticsAssert($base['summary']['abnormal_percentage'] === 66.7, 'Persentase abnormal tidak sesuai denominator hasil yang dinilai.');
analyticsAssert($base['summary']['examinations_with_results'] === 4, 'Jumlah pemeriksaan yang memiliki hasil tidak sesuai.');
analyticsAssert($base['summary']['examinations_with_results_percentage'] === 66.7, 'Persentase pemeriksaan yang memiliki hasil tidak sesuai.');
analyticsAssert($base['clinical']['totals']['total_results'] === 4, 'Hasil modern dan legacy dihitung ganda atau hilang.');
analyticsAssert($base['clinical']['totals']['assessed_results'] === 3, 'Jumlah hasil yang berhasil dinilai tidak sesuai.');
analyticsAssert($base['clinical']['totals']['parameters_without_reference'] === 1, 'Hasil tanpa nilai rujukan tidak terdeteksi.');
$hgb = analyticsFindRow($base['clinical']['parameters'], 'HGB');
analyticsAssert($hgb['total_results'] === 1 && $hgb['low_results'] === 1, 'Prioritas hasil modern terhadap legacy atau interpretasi HGB gagal.');
$gds = analyticsFindRow($base['clinical']['parameters'], 'GDS');
analyticsAssert($gds['high_results'] === 1, 'Interpretasi GDS tinggi tidak sesuai nilai rujukan aktif.');
$patientCumulative = CumulativeResultRepository::forPatient(1, $pdo, ['limit' => 'all']);
analyticsAssert(
    ($patientCumulative['results'][101]['HGB']['interpretation'] ?? null) === 'low',
    'Interpretasi dashboard tidak konsisten dengan halaman hasil kumulatif untuk sampel HGB.'
);
analyticsAssert($base['quality']['draft_examinations'] === 1, 'Indikator pemeriksaan draft tidak sesuai.');
analyticsAssert($base['quality']['completed_without_results'] === 1, 'Indikator pemeriksaan tanpa hasil tidak sesuai.');
analyticsAssert($base['quality']['incomplete_modern_panels'] === 1, 'Indikator panel modern belum lengkap tidak sesuai.');
analyticsAssert($base['quality']['missing_birth_date'] === 1, 'Indikator tanggal lahir belum lengkap tidak sesuai.');
analyticsAssert($base['quality']['missing_sex'] === 1, 'Indikator jenis kelamin belum lengkap tidak sesuai.');
analyticsAssert($base['quality']['missing_insurance'] === 3, 'Indikator asuransi belum lengkap tidak sesuai.');
$exportRows = AnalyticsAggregateExporter::rows($base);
analyticsAssert(AnalyticsAggregateExporter::headers() === ['Bagian', 'Kelompok', 'Metrik', 'Jumlah', 'Persentase (%)', 'Keterangan'], 'Header ekspor agregat tidak sesuai.');
analyticsAssert(analyticsFindExportRow($exportRows, 'Ringkasan', '', 'Total pemeriksaan')[3] === 6, 'Ekspor agregat tidak konsisten dengan total pemeriksaan dashboard.');
analyticsAssert(analyticsFindExportRow($exportRows, 'Penjamin', 'BPJS Kesehatan', 'Pasien unik')[3] === 1, 'Ekspor agregat tidak konsisten dengan pasien unik penjamin.');
analyticsAssert(analyticsFindExportRow($exportRows, 'Demografi usia', 'Dewasa muda (18–39 tahun)', 'Pasien unik')[3] === 1, 'Ekspor agregat tidak konsisten dengan demografi dashboard.');
analyticsAssert(AnalyticsAggregateExporter::auditContext($base, 'csv')['aggregate_rows'] === count($exportRows), 'Konteks audit ekspor tidak memuat jumlah baris agregat yang benar.');
$exportSheets = AnalyticsAggregateExporter::sheets($base);
analyticsAssert(
    array_column($exportSheets, 'name') === ['Ringkasan', 'Tren', 'Pemakaian', 'Penjamin', 'Demografi', 'Hasil abnormal', 'Kualitas data'],
    'Workbook ekspor harus memiliki tab agregat yang terpisah.'
);
analyticsAssert(
    count($exportSheets[1]['rows']) === count($base['operational']['trend']['rows']),
    'Tab tren harus memuat setiap periode pada dashboard.'
);
analyticsAssert(
    $exportSheets[5]['headers'] === ['Parameter', 'Kode', 'Kategori', 'Jumlah hasil', 'Rendah', 'Tinggi / abnormal', 'Belum dinilai', 'Persentase abnormal (%)'],
    'Header tab hasil abnormal tidak sesuai tabel dashboard.'
);
analyticsAssert(
    analyticsFindSheetRow($exportSheets[3]['rows'], 'BPJS Kesehatan')[3] === 1,
    'Tab penjamin harus memuat jumlah pasien unik.'
);

$hematology = AnalyticsRepository::dashboard([
    'date_from' => '2026-08-01',
    'date_to' => '2026-08-31',
    'panel' => 'hematology_complete',
], $pdo);
analyticsAssert($hematology['clinical']['totals']['total_results'] === 1, 'Filter kategori tidak diterapkan ke hasil parameter.');
analyticsAssert($hematology['summary']['abnormal_results'] === 1, 'KPI abnormal tidak mengikuti filter kategori.');

$longPeriod = AnalyticsRepository::dashboard([
    'date_from' => '2026-07-01',
    'date_to' => '2026-08-31',
], $pdo);
analyticsAssert($longPeriod['operational']['trend']['grouping'] === 'week', 'Periode 32–180 hari seharusnya dikelompokkan per minggu.');
analyticsAssert(array_sum(array_column($longPeriod['operational']['trend']['rows'], 'total_examinations')) === 7, 'Total tren mingguan tidak konsisten dengan KPI.');

analyticsAssert(analyticsTotal(['type' => 'mcu_general'], $pdo) === 4, 'Filter jenis pemeriksaan gagal.');
analyticsAssert(analyticsTotal(['insurance_type' => 'BPJS Kesehatan'], $pdo) === 3, 'Filter asuransi gagal.');
analyticsAssert(analyticsTotal(['insurance_type' => 'Umum'], $pdo) === 0, 'Filter asuransi tanpa data tetap harus valid.');
analyticsAssert(analyticsTotal(['insurance_type' => AnalyticsRepository::EMPTY_INSURANCE], $pdo) === 3, 'Filter asuransi kosong gagal.');
analyticsAssert(analyticsTotal(['sex' => 'L'], $pdo) === 3, 'Filter jenis kelamin gagal.');
analyticsAssert(analyticsTotal(['sex' => 'unknown'], $pdo) === 1, 'Filter jenis kelamin tidak diketahui gagal.');
analyticsAssert(analyticsTotal(['age_group' => '18_39'], $pdo) === 3, 'Filter kelompok usia 18–39 gagal.');
analyticsAssert(analyticsTotal(['age_group' => '40_59'], $pdo) === 2, 'Filter kelompok usia 40–59 gagal.');
analyticsAssert(analyticsTotal(['age_group' => 'unknown'], $pdo) === 1, 'Filter usia tidak diketahui gagal.');
analyticsAssert(analyticsTotal(['status' => 'completed'], $pdo) === 4, 'Filter status tersimpan gagal.');
analyticsAssert(analyticsTotal(['status' => 'finalized'], $pdo) === 1, 'Filter status final gagal.');
analyticsAssert(analyticsTotal(['status' => 'draft'], $pdo) === 1, 'Filter status legacy gagal.');
analyticsAssert(analyticsTotal(['panel' => 'hematology_complete'], $pdo) === 1, 'Filter panel modern gagal atau nilai nol dianggap kosong.');
analyticsAssert(analyticsTotal(['panel' => 'clinical_chemistry'], $pdo) === 1, 'Filter panel legacy gagal.');
analyticsAssert(analyticsTotal(['panel' => 'drug_screen'], $pdo) === 1, 'Filter skrining narkoba legacy gagal.');
analyticsAssert(analyticsTotal(['panel' => 'additional_rare'], $pdo) === 1, 'Filter panel tambahan modern gagal.');
analyticsAssert(analyticsTotal(['date_from' => '2026-08-10', 'date_to' => '2026-08-10'], $pdo) === 1, 'Batas tanggal inklusif gagal.');

analyticsExpectInvalid(['date_from' => '31-08-2026'], $pdo);
analyticsExpectInvalid(['date_from' => '2026-08-31', 'date_to' => '2026-08-01'], $pdo);
analyticsExpectInvalid(['type' => 'mcu_general" OR 1=1'], $pdo);
analyticsExpectInvalid(['panel' => 'unknown_panel'], $pdo);
analyticsExpectInvalid(['insurance_type' => 'Asuransi tidak terdaftar'], $pdo);
analyticsExpectInvalid(['sex' => 'X'], $pdo);
analyticsExpectInvalid(['age_group' => '20_40'], $pdo);
analyticsExpectInvalid(['type' => ['mcu_general']], $pdo);

echo "AnalyticsRepository integration: OK\n";
