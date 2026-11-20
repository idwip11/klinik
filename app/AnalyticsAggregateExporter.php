<?php

declare(strict_types=1);

final class AnalyticsAggregateExporter
{
    /** @return list<string> */
    public static function headers(): array
    {
        return ['Bagian', 'Kelompok', 'Metrik', 'Jumlah', 'Persentase (%)', 'Keterangan'];
    }

    /** @param array<string,mixed> $dashboard @return list<list<string|int|float>> */
    public static function rows(array $dashboard): array
    {
        $filters = $dashboard['filters'];
        $summary = $dashboard['summary'];
        $operational = $dashboard['operational'];
        $clinical = $dashboard['clinical'];
        $quality = $dashboard['quality'];

        $rows = [
            ['Filter', 'Periode', 'Dari tanggal', $filters['date_from'], '', 'Tanggal pemeriksaan bersifat inklusif.'],
            ['Filter', 'Periode', 'Sampai tanggal', $filters['date_to'], '', 'Tanggal pemeriksaan bersifat inklusif.'],
            ['Filter', 'Jenis pemeriksaan', self::filterLabel('type', $filters['type']), '', '', ''],
            ['Filter', 'Kategori pemeriksaan', self::filterLabel('panel', $filters['panel']), '', '', ''],
            ['Filter', 'Asuransi / penjamin', self::filterLabel('insurance_type', $filters['insurance_type']), '', '', 'Profil penjamin pasien saat ekspor dibuat.'],
            ['Filter', 'Jenis kelamin', self::filterLabel('sex', $filters['sex']), '', '', ''],
            ['Filter', 'Kelompok usia', self::filterLabel('age_group', $filters['age_group']), '', '', ''],
            ['Filter', 'Status hasil', self::filterLabel('status', $filters['status']), '', '', ''],
            ['Ringkasan', '', 'Total pemeriksaan', (int) $summary['total_examinations'], '', 'Pasien arsip tidak termasuk.'],
            ['Ringkasan', '', 'Pasien unik', (int) $summary['unique_patients'], '', 'Satu pasien dihitung satu kali.'],
            ['Ringkasan', '', 'Hasil abnormal', (int) $summary['abnormal_results'], (float) $summary['abnormal_percentage'], 'Dari hasil yang berhasil dinilai.'],
            ['Ringkasan', '', 'Pemeriksaan memiliki hasil', (int) $summary['examinations_with_results'], (float) $summary['examinations_with_results_percentage'], 'Minimal satu hasil parameter tidak kosong.'],
        ];

        foreach ($operational['trend']['rows'] as $row) {
            $rows[] = ['Tren operasional', (string) $row['label'], 'Pemeriksaan', (int) $row['total_examinations'], '', 'Pengelompokan ' . $operational['trend']['grouping'] . '.'];
            $rows[] = ['Tren operasional', (string) $row['label'], 'Pasien unik', (int) $row['unique_patients'], '', ''];
        }

        foreach ($operational['examination_types'] as $row) {
            $rows[] = ['Jenis pemeriksaan', (string) $row['label'], 'Pemeriksaan', (int) $row['total'], (float) $row['percentage'], ''];
        }
        foreach ($operational['panels'] as $row) {
            $rows[] = ['Kategori pemeriksaan', (string) $row['label'], 'Pemeriksaan', (int) $row['total'], (float) $row['percentage'], 'Satu pemeriksaan dapat masuk lebih dari satu kategori.'];
        }
        foreach ($operational['insurance'] as $row) {
            $rows[] = ['Penjamin', (string) $row['label'], 'Pemeriksaan', (int) $row['total'], (float) $row['percentage'], 'Profil penjamin pasien saat ekspor dibuat.'];
            $rows[] = ['Penjamin', (string) $row['label'], 'Pasien unik', (int) $row['unique_patients'], (float) $row['unique_percentage'], ''];
        }
        foreach ($operational['demographics']['age_groups'] as $row) {
            $rows[] = ['Demografi usia', (string) $row['label'], 'Pasien unik', (int) $row['total'], (float) $row['percentage'], 'Pemeriksaan terakhir pasien dalam periode.'];
        }
        foreach ($operational['demographics']['sexes'] as $row) {
            $rows[] = ['Demografi jenis kelamin', (string) $row['label'], 'Pasien unik', (int) $row['total'], (float) $row['percentage'], 'Pemeriksaan terakhir pasien dalam periode.'];
        }
        foreach ($clinical['parameters'] as $row) {
            $label = (string) $row['label'];
            if (($row['code'] ?? '') !== '') {
                $label .= ' (' . $row['code'] . ')';
            }
            $rows[] = ['Hasil abnormal per parameter', $label, 'Jumlah hasil', (int) $row['total_results'], '', ''];
            $rows[] = ['Hasil abnormal per parameter', $label, 'Rendah', (int) $row['low_results'], '', ''];
            $rows[] = ['Hasil abnormal per parameter', $label, 'Tinggi / abnormal', (int) $row['high_results'] + (int) $row['qualitative_abnormal_results'], '', ''];
            $rows[] = ['Hasil abnormal per parameter', $label, 'Belum dinilai', (int) $row['not_assessed_results'], '', 'Tidak dihitung sebagai normal.'];
            $rows[] = ['Hasil abnormal per parameter', $label, 'Tanpa penilaian', (int) $row['not_applicable_results'], '', 'Golongan darah/Rhesus tidak memerlukan nilai rujukan.'];
            $rows[] = ['Hasil abnormal per parameter', $label, 'Persentase abnormal', (int) $row['abnormal_results'], $row['not_applicable_results'] === $row['total_results'] ? '' : (float) $row['abnormal_percentage'], 'Denominator hanya hasil yang berhasil dinilai.'];
        }

        foreach (self::qualityMetrics($quality) as $metric => [$total, $note]) {
            $rows[] = ['Kualitas data', '', $metric, $total, '', $note];
        }

        return $rows;
    }

    /**
     * Struktur workbook Excel. Tab dipisahkan berdasarkan area dashboard agar
     * rekap tetap mudah dibaca saat periode menghasilkan banyak data tren.
     *
     * @param array<string,mixed> $dashboard
     * @return list<array{name:string,headers:list<string>,rows:list<list<string|int|float>>,widths:list<int>}>
     */
    public static function sheets(array $dashboard): array
    {
        $filters = $dashboard['filters'];
        $summary = $dashboard['summary'];
        $operational = $dashboard['operational'];
        $clinical = $dashboard['clinical'];

        $summaryRows = [
            ['Filter', 'Dari tanggal', (string) $filters['date_from'], 'Tanggal pemeriksaan bersifat inklusif.'],
            ['Filter', 'Sampai tanggal', (string) $filters['date_to'], 'Tanggal pemeriksaan bersifat inklusif.'],
            ['Filter', 'Jenis pemeriksaan', self::filterLabel('type', (string) $filters['type']), ''],
            ['Filter', 'Kategori pemeriksaan', self::filterLabel('panel', (string) $filters['panel']), ''],
            ['Filter', 'Asuransi / penjamin', self::filterLabel('insurance_type', (string) $filters['insurance_type']), 'Profil penjamin pasien saat ekspor dibuat.'],
            ['Filter', 'Jenis kelamin', self::filterLabel('sex', (string) $filters['sex']), ''],
            ['Filter', 'Kelompok usia', self::filterLabel('age_group', (string) $filters['age_group']), ''],
            ['Filter', 'Status hasil', self::filterLabel('status', (string) $filters['status']), ''],
            ['Ringkasan', 'Total pemeriksaan', (int) $summary['total_examinations'], 'Pasien arsip tidak termasuk.'],
            ['Ringkasan', 'Pasien unik', (int) $summary['unique_patients'], 'Satu pasien dihitung satu kali.'],
            ['Ringkasan', 'Hasil abnormal', (int) $summary['abnormal_results'], self::percentageText((float) $summary['abnormal_percentage'])],
            ['Ringkasan', 'Pemeriksaan memiliki hasil', (int) $summary['examinations_with_results'], self::percentageText((float) $summary['examinations_with_results_percentage'])],
        ];

        $trendRows = [];
        foreach ($operational['trend']['rows'] as $row) {
            $trendRows[] = [
                (string) $row['label'],
                (int) $row['total_examinations'],
                (int) $row['unique_patients'],
            ];
        }

        $usageRows = [];
        foreach ($operational['examination_types'] as $row) {
            $usageRows[] = ['Jenis pemeriksaan', (string) $row['label'], (int) $row['total'], (float) $row['percentage'], ''];
        }
        foreach ($operational['panels'] as $row) {
            $usageRows[] = ['Kategori pemeriksaan', (string) $row['label'], (int) $row['total'], (float) $row['percentage'], 'Satu pemeriksaan dapat masuk lebih dari satu kategori.'];
        }

        $insuranceRows = [];
        foreach ($operational['insurance'] as $row) {
            $insuranceRows[] = [
                (string) $row['label'],
                (int) $row['total'],
                (float) $row['percentage'],
                (int) $row['unique_patients'],
                (float) $row['unique_percentage'],
                'Profil penjamin pasien saat ekspor dibuat.',
            ];
        }

        $demographicRows = [];
        foreach ($operational['demographics']['age_groups'] as $row) {
            $demographicRows[] = ['Kelompok usia', (string) $row['label'], (int) $row['total'], (float) $row['percentage']];
        }
        foreach ($operational['demographics']['sexes'] as $row) {
            $demographicRows[] = ['Jenis kelamin', (string) $row['label'], (int) $row['total'], (float) $row['percentage']];
        }

        $abnormalRows = [];
        foreach ($clinical['parameters'] as $row) {
            $abnormalRows[] = [
                (string) $row['label'],
                (string) $row['code'],
                (string) $row['panel_name'],
                (int) $row['total_results'],
                (int) $row['low_results'],
                (int) $row['high_results'] + (int) $row['qualitative_abnormal_results'],
                (int) $row['not_assessed_results'],
                (int) $row['not_applicable_results'],
                $row['not_applicable_results'] === $row['total_results'] ? '' : (float) $row['abnormal_percentage'],
            ];
        }

        $qualityRows = [];
        foreach (self::qualityMetrics($dashboard['quality']) as $metric => [$total, $note]) {
            $qualityRows[] = [$metric, $total, $note];
        }

        return [
            [
                'name' => 'Ringkasan',
                'headers' => ['Bagian', 'Metrik', 'Nilai', 'Keterangan'],
                'rows' => $summaryRows,
                'widths' => [16, 32, 26, 54],
            ],
            [
                'name' => 'Tren',
                'headers' => ['Periode', 'Pemeriksaan', 'Pasien unik'],
                'rows' => $trendRows,
                'widths' => [20, 18, 18],
            ],
            [
                'name' => 'Pemakaian',
                'headers' => ['Kelompok', 'Item', 'Pemeriksaan', 'Persentase (%)', 'Keterangan'],
                'rows' => $usageRows,
                'widths' => [24, 32, 18, 18, 54],
            ],
            [
                'name' => 'Penjamin',
                'headers' => ['Penjamin', 'Pemeriksaan', '% pemeriksaan', 'Pasien unik', '% pasien', 'Keterangan'],
                'rows' => $insuranceRows,
                'widths' => [28, 18, 18, 18, 16, 54],
            ],
            [
                'name' => 'Demografi',
                'headers' => ['Dimensi', 'Kelompok', 'Pasien unik', 'Persentase (%)'],
                'rows' => $demographicRows,
                'widths' => [22, 34, 18, 18],
            ],
            [
                'name' => 'Hasil abnormal',
                'headers' => ['Parameter', 'Kode', 'Kategori', 'Jumlah hasil', 'Rendah', 'Tinggi / abnormal', 'Belum dinilai', 'Tanpa penilaian', 'Persentase abnormal (%)'],
                'rows' => $abnormalRows,
                'widths' => [30, 20, 32, 16, 14, 22, 18, 20, 26],
            ],
            [
                'name' => 'Kualitas data',
                'headers' => ['Indikator', 'Jumlah', 'Keterangan'],
                'rows' => $qualityRows,
                'widths' => [38, 16, 60],
            ],
        ];
    }

    /** @param array<string,mixed> $dashboard @return array<string,mixed> */
    public static function auditContext(array $dashboard, string $format): array
    {
        return [
            'format' => $format,
            'filters' => $dashboard['filters'],
            'summary' => [
                'total_examinations' => $dashboard['summary']['total_examinations'],
                'unique_patients' => $dashboard['summary']['unique_patients'],
                'abnormal_results' => $dashboard['summary']['abnormal_results'],
            ],
            'aggregate_rows' => count(self::rows($dashboard)),
        ];
    }

    /** @param array<string,mixed> $dashboard */
    public static function downloadCsv(array $dashboard): never
    {
        self::downloadCsvRows(self::headers(), self::rows($dashboard), self::filename('csv'));
    }

    /** @param array<string,mixed> $dashboard */
    public static function downloadXlsx(array $dashboard): never
    {
        ReportExcelExporter::downloadWorkbook(
            self::filename('xlsx'),
            self::sheets($dashboard),
            'Analisis laboratorium agregat'
        );
    }

    /** @param list<string> $headers @param list<list<string|int|float>> $rows */
    private static function downloadCsvRows(array $headers, array $rows, string $filename): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('Berkas ekspor CSV tidak dapat dibuat.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($output, array_map([self::class, 'safeCsv'], $row), ',', '"', '');
        }
        fclose($output);
        exit;
    }

    private static function filename(string $extension): string
    {
        return 'analisis-laboratorium-agregat-' . date('Y-m-d') . '.' . $extension;
    }

    private static function safeCsv(string|int|float $value): string|int|float
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }

    /** @param array<string,int> $quality @return array<string,array{0:int,1:string}> */
    private static function qualityMetrics(array $quality): array
    {
        return [
            'Pemeriksaan draft' => [(int) $quality['draft_examinations'], 'Termasuk data legacy terverifikasi pada filter.'],
            'Pemeriksaan tanpa hasil' => [(int) $quality['completed_without_results'], 'Hanya status tersimpan atau final.'],
            'Panel modern belum lengkap' => [(int) $quality['incomplete_modern_panels'], 'Dibandingkan dengan parameter yang diharapkan; panel otomatis yang seluruh hasilnya kosong tidak dihitung.'],
            'Pemeriksaan tanpa tanggal lahir valid' => [(int) $quality['missing_birth_date'], 'Dihitung per pemeriksaan.'],
            'Pemeriksaan tanpa jenis kelamin' => [(int) $quality['missing_sex'], 'Nilai kosong atau di luar L/P.'],
            'Pemeriksaan tanpa penjamin' => [(int) $quality['missing_insurance'], 'Profil penjamin pasien belum diisi.'],
            'Hasil tanpa rujukan aktif' => [(int) $quality['parameters_without_reference'], 'Tidak dihitung sebagai normal maupun abnormal.'],
        ];
    }

    private static function filterLabel(string $filter, string $value): string
    {
        return match ($filter) {
            'type' => $value === '' ? 'Semua jenis' : (ExaminationType::labels()[$value] ?? $value),
            'panel' => $value === '' ? 'Semua kategori' : $value,
            'insurance_type' => $value === '' ? 'Semua penjamin' : ($value === AnalyticsRepository::EMPTY_INSURANCE ? 'Belum diisi' : $value),
            'sex' => match ($value) { 'L' => 'Laki-laki', 'P' => 'Perempuan', 'unknown' => 'Tidak diketahui', default => 'Semua jenis kelamin' },
            'age_group' => match ($value) { '0_17' => 'Anak (0–17 tahun)', '18_39' => 'Dewasa muda (18–39 tahun)', '40_59' => 'Dewasa (40–59 tahun)', '60_plus' => 'Lansia (60 tahun ke atas)', 'unknown' => 'Tidak diketahui', default => 'Semua kelompok usia' },
            'status' => match ($value) { 'draft' => 'Data legacy terverifikasi', 'completed' => 'Tersimpan', 'finalized' => 'Final', default => 'Semua data tersimpan' },
            default => $value,
        };
    }

    private static function percentageText(float $value): string
    {
        return number_format($value, 1, ',', '.') . '%';
    }
}
