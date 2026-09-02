<?php
/** @var array<string,mixed> $analyticsData */
/** @var string|null $filterError */
$filters = $analyticsData['filters'];
$options = $analyticsData['options'];
$summary = $analyticsData['summary'];
$operational = $analyticsData['operational'];
$clinical = $analyticsData['clinical'];
$quality = $analyticsData['quality'];
$formatPercentage = static fn (float $value): string => number_format($value, 1, ',', '.') . '%';
$trendValues = array_map(static fn (array $row): int => $row['total_examinations'], $operational['trend']['rows']);
$trendMaximum = max(array_merge([1], $trendValues));
$exportQuery = http_build_query($filters);
$hasRecordedInsurance = array_filter(
    $operational['insurance'],
    static fn (array $row): bool => $row['code'] !== AnalyticsRepository::EMPTY_INSURANCE
) !== [];
?>

<section class="page-heading analytics-heading">
    <p class="eyebrow">Analisis</p>
    <h1>Analisis laboratorium</h1>
    <p>Ringkasan agregat pemeriksaan untuk mendukung pemantauan operasional dan kualitas data laboratorium.</p>
</section>

<?php if ($filterError !== null): ?>
    <div class="notice notice-error" role="alert"><?= View::e($filterError) ?> Filter dikembalikan ke periode bawaan.</div>
<?php endif; ?>

<section class="form-section analytics-filter-section" aria-labelledby="analytics-filter-title">
    <div class="analytics-section-heading">
        <div>
            <h2 id="analytics-filter-title">Filter analisis</h2>
            <p class="help-text">Semua ringkasan Analisis menggunakan kumpulan pemeriksaan dan filter yang sama.</p>
        </div>
    </div>

    <form method="get" action="/analytics" class="data-form analytics-filter-form">
        <div class="analytics-filter-grid">
            <label>Dari tanggal
                <input type="date" name="date_from" value="<?= View::e($filters['date_from']) ?>" required>
            </label>
            <label>Sampai tanggal
                <input type="date" name="date_to" value="<?= View::e($filters['date_to']) ?>" required>
            </label>
            <label>Jenis pemeriksaan
                <select name="type">
                    <option value="">Semua jenis</option>
                    <?php foreach ($options['examination_types'] as $value => $label): ?>
                        <option value="<?= View::e($value) ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Kategori pemeriksaan
                <select name="panel">
                    <option value="">Semua kategori</option>
                    <?php foreach ($options['panels'] as $panel): ?>
                        <option value="<?= View::e($panel['code']) ?>" <?= $filters['panel'] === $panel['code'] ? 'selected' : '' ?>><?= View::e($panel['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Asuransi / penjamin
                <select name="insurance_type">
                    <option value="">Semua penjamin</option>
                    <option value="<?= View::e(AnalyticsRepository::EMPTY_INSURANCE) ?>" <?= $filters['insurance_type'] === AnalyticsRepository::EMPTY_INSURANCE ? 'selected' : '' ?>>Belum diisi</option>
                    <?php foreach ($options['insurance_types'] as $insuranceType): ?>
                        <option value="<?= View::e($insuranceType) ?>" <?= $filters['insurance_type'] === $insuranceType ? 'selected' : '' ?>><?= View::e($insuranceType) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Jenis kelamin
                <select name="sex">
                    <option value="">Semua jenis kelamin</option>
                    <?php foreach ($options['sexes'] as $value => $label): ?>
                        <option value="<?= View::e($value) ?>" <?= $filters['sex'] === $value ? 'selected' : '' ?>><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Kelompok usia
                <select name="age_group">
                    <option value="">Semua kelompok usia</option>
                    <?php foreach ($options['age_groups'] as $value => $label): ?>
                        <option value="<?= View::e($value) ?>" <?= $filters['age_group'] === $value ? 'selected' : '' ?>><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Status hasil
                <select name="status">
                    <?php foreach ($options['statuses'] as $value => $label): ?>
                        <option value="<?= View::e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="form-actions analytics-filter-actions">
            <a href="/analytics">Reset</a>
            <button type="submit">Terapkan filter</button>
        </div>
    </form>
</section>

<section class="analytics-result-section" aria-labelledby="analytics-result-title">
    <div class="analytics-section-heading">
        <div>
            <p class="eyebrow">Ringkasan terfilter</p>
            <h2 id="analytics-result-title">Cakupan pemeriksaan</h2>
        </div>
        <div class="analytics-result-actions">
            <a class="analytics-action-link" href="/analytics/help">Bantuan metrik</a>
            <a class="analytics-action-link" href="/analytics/export?<?= View::e($exportQuery) ?>&amp;format=csv">Ekspor CSV</a>
            <a class="button-link analytics-export-button" href="/analytics/export?<?= View::e($exportQuery) ?>&amp;format=xlsx">Ekspor Excel</a>
        </div>
    </div>
    <div class="analytics-kpi-grid">
        <article class="analytics-kpi-card">
            <span class="analytics-kpi-label">Total pemeriksaan</span>
            <strong><?= View::e(View::formatNumber($summary['total_examinations'])) ?></strong>
            <small><?= View::e($options['statuses'][$filters['status']]) ?>, pasien aktif</small>
        </article>
        <article class="analytics-kpi-card">
            <span class="analytics-kpi-label">Pasien unik</span>
            <strong><?= View::e(View::formatNumber($summary['unique_patients'])) ?></strong>
            <small>Satu pasien dihitung satu kali pada periode yang sama.</small>
        </article>
        <article class="analytics-kpi-card analytics-kpi-card-alert">
            <span class="analytics-kpi-label">Hasil abnormal</span>
            <strong><?= View::e(View::formatNumber($summary['abnormal_results'])) ?></strong>
            <small><?= View::e($formatPercentage($summary['abnormal_percentage'])) ?> dari <?= View::e(View::formatNumber($clinical['totals']['assessed_results'])) ?> hasil yang berhasil dinilai.</small>
        </article>
        <article class="analytics-kpi-card">
            <span class="analytics-kpi-label">Pemeriksaan memiliki hasil</span>
            <strong><?= View::e($formatPercentage($summary['examinations_with_results_percentage'])) ?></strong>
            <small><?= View::e(View::formatNumber($summary['examinations_with_results'])) ?> dari <?= View::e(View::formatNumber($summary['total_examinations'])) ?> pemeriksaan memiliki minimal satu hasil.</small>
        </article>
    </div>

    <?php if ($summary['total_examinations'] === 0): ?>
        <div class="empty-state analytics-empty-state">
            <strong>Tidak ada pemeriksaan yang sesuai.</strong>
            <span>Ubah periode atau pilihan filter untuk melihat data lain.</span>
        </div>
    <?php else: ?>
        <p class="analytics-scope-note">
            Data ditemukan dari <?= View::e(View::formatDate($summary['first_examined_on'])) ?> sampai <?= View::e(View::formatDate($summary['last_examined_on'])) ?>.
        </p>
    <?php endif; ?>
</section>

<?php if ($summary['total_examinations'] > 0): ?>
    <section class="analytics-dashboard-grid" aria-label="Dashboard operasional dan demografi">
        <article class="analytics-panel analytics-panel-wide">
            <div class="analytics-section-heading">
                <div>
                    <p class="eyebrow">Operasional</p>
                    <h2>Tren pemeriksaan</h2>
                    <p class="help-text">Dikelompokkan per <?= $operational['trend']['grouping'] === 'day' ? 'hari' : ($operational['trend']['grouping'] === 'week' ? 'minggu' : 'bulan') ?> sesuai panjang periode.</p>
                </div>
            </div>
            <ol class="analytics-bar-list" aria-label="Grafik tren pemeriksaan">
                <?php foreach ($operational['trend']['rows'] as $row): ?>
                    <li>
                        <span class="analytics-bar-label"><?= View::e($row['label']) ?></span>
                        <span class="analytics-bar-track" aria-hidden="true"><span class="analytics-bar-fill" style="width: <?= View::e((string) round(($row['total_examinations'] / $trendMaximum) * 100, 1)) ?>%"></span></span>
                        <strong><?= View::e(View::formatNumber($row['total_examinations'])) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ol>
            <div class="table-wrap analytics-table-wrap">
                <table class="analytics-table">
                    <thead><tr><th>Periode</th><th>Pemeriksaan</th><th>Pasien unik</th></tr></thead>
                    <tbody>
                    <?php foreach ($operational['trend']['rows'] as $row): ?>
                        <tr>
                            <td><?= View::e($row['label']) ?></td>
                            <td><?= View::e(View::formatNumber($row['total_examinations'])) ?></td>
                            <td><?= View::e(View::formatNumber($row['unique_patients'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="analytics-panel">
            <div class="analytics-section-heading">
                <div>
                    <p class="eyebrow">Pemakaian layanan</p>
                    <h2>Jenis pemeriksaan terbanyak</h2>
                </div>
            </div>
            <div class="table-wrap analytics-table-wrap">
                <table class="analytics-table">
                    <thead><tr><th>Jenis</th><th>Jumlah</th><th>Dari total</th></tr></thead>
                    <tbody>
                    <?php foreach ($operational['examination_types'] as $row): ?>
                        <tr><td><?= View::e($row['label']) ?></td><td><?= View::e(View::formatNumber($row['total'])) ?></td><td><?= View::e($formatPercentage($row['percentage'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="analytics-panel">
            <div class="analytics-section-heading">
                <div>
                    <p class="eyebrow">Pemakaian layanan</p>
                    <h2>Kategori pemeriksaan terbanyak</h2>
                    <p class="help-text">Satu pemeriksaan dapat masuk lebih dari satu kategori.</p>
                </div>
            </div>
            <div class="table-wrap analytics-table-wrap">
                <table class="analytics-table">
                    <thead><tr><th>Kategori</th><th>Jumlah</th><th>Dari total</th></tr></thead>
                    <tbody>
                    <?php foreach ($operational['panels'] as $row): ?>
                        <tr><td><?= View::e($row['label']) ?></td><td><?= View::e(View::formatNumber($row['total'])) ?></td><td><?= View::e($formatPercentage($row['percentage'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="analytics-panel">
            <div class="analytics-section-heading">
                <div>
                    <p class="eyebrow">Penjamin</p>
                    <h2>Pemeriksaan dan pasien menurut asuransi</h2>
                    <p class="help-text">Menggunakan profil penjamin pasien saat ini, bukan snapshot historis kunjungan.</p>
                </div>
            </div>
            <?php if (!$hasRecordedInsurance): ?>
                <p class="analytics-data-notice"><strong>Data penjamin belum tersedia.</strong> Lengkapi penjamin pada profil pasien agar kategori seperti BPJS Kesehatan muncul pada grafik ini.</p>
            <?php endif; ?>
            <div class="analytics-insurance-grid">
                <div class="analytics-insurance-metric">
                    <h3>Jumlah pemeriksaan</h3>
                    <ol class="analytics-bar-list" aria-label="Grafik pemeriksaan menurut asuransi">
                        <?php foreach ($operational['insurance'] as $row): ?>
                            <li>
                                <span class="analytics-bar-label"><?= View::e($row['label']) ?></span>
                                <span class="analytics-bar-track" aria-hidden="true"><span class="analytics-bar-fill analytics-bar-fill-secondary" style="width: <?= View::e((string) $row['percentage']) ?>%"></span></span>
                                <strong><?= View::e(View::formatNumber($row['total'])) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <div class="analytics-insurance-metric">
                    <h3>Pasien unik</h3>
                    <ol class="analytics-bar-list" aria-label="Grafik pasien unik menurut asuransi">
                        <?php foreach ($operational['insurance'] as $row): ?>
                            <li>
                                <span class="analytics-bar-label"><?= View::e($row['label']) ?></span>
                                <span class="analytics-bar-track" aria-hidden="true"><span class="analytics-bar-fill analytics-bar-fill-demographic" style="width: <?= View::e((string) $row['unique_percentage']) ?>%"></span></span>
                                <strong><?= View::e(View::formatNumber($row['unique_patients'])) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
            <div class="table-wrap analytics-table-wrap">
                <table class="analytics-table analytics-insurance-table">
                    <thead><tr><th>Penjamin</th><th>Pemeriksaan</th><th>% pemeriksaan</th><th>Pasien unik</th><th>% pasien</th></tr></thead>
                    <tbody>
                    <?php foreach ($operational['insurance'] as $row): ?>
                        <tr><td data-label="Penjamin"><?= View::e($row['label']) ?></td><td data-label="Pemeriksaan"><?= View::e(View::formatNumber($row['total'])) ?></td><td data-label="% pemeriksaan"><?= View::e($formatPercentage($row['percentage'])) ?></td><td data-label="Pasien unik"><?= View::e(View::formatNumber($row['unique_patients'])) ?></td><td data-label="% pasien"><?= View::e($formatPercentage($row['unique_percentage'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="analytics-panel analytics-panel-wide analytics-demographic-panel">
            <div class="analytics-section-heading">
                <div>
                    <p class="eyebrow">Demografi</p>
                    <h2>Pasien unik menurut usia dan jenis kelamin</h2>
                    <p class="help-text">Setiap pasien ditempatkan satu kali menurut pemeriksaan terakhirnya dalam periode ini.</p>
                </div>
            </div>
            <div class="analytics-demographic-grid">
                <section class="analytics-demographic-column" aria-labelledby="analytics-age-title">
                    <h3 id="analytics-age-title">Kelompok usia</h3>
                    <ol class="analytics-bar-list" aria-label="Grafik kelompok usia">
                        <?php foreach ($operational['demographics']['age_groups'] as $row): ?>
                            <li>
                                <span class="analytics-bar-label"><?= View::e($row['label']) ?></span>
                                <span class="analytics-bar-track" aria-hidden="true"><span class="analytics-bar-fill analytics-bar-fill-demographic" style="width: <?= View::e((string) $row['percentage']) ?>%"></span></span>
                                <span class="analytics-bar-value"><strong><?= View::e(View::formatNumber($row['total'])) ?></strong><small><?= View::e($formatPercentage($row['percentage'])) ?></small></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <details class="analytics-data-details">
                        <summary>Lihat tabel angka kelompok usia</summary>
                        <div class="table-wrap analytics-table-wrap">
                            <table class="analytics-table analytics-demographic-table"><thead><tr><th>Kelompok usia</th><th>Pasien</th><th>Persentase</th></tr></thead><tbody>
                            <?php foreach ($operational['demographics']['age_groups'] as $row): ?>
                                <tr><td data-label="Kelompok usia"><?= View::e($row['label']) ?></td><td data-label="Pasien"><?= View::e(View::formatNumber($row['total'])) ?></td><td data-label="Persentase"><?= View::e($formatPercentage($row['percentage'])) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody></table>
                        </div>
                    </details>
                </section>
                <section class="analytics-demographic-column" aria-labelledby="analytics-sex-title">
                    <h3 id="analytics-sex-title">Jenis kelamin</h3>
                    <ol class="analytics-bar-list" aria-label="Grafik jenis kelamin">
                        <?php foreach ($operational['demographics']['sexes'] as $row): ?>
                            <li>
                                <span class="analytics-bar-label"><?= View::e($row['label']) ?></span>
                                <span class="analytics-bar-track" aria-hidden="true"><span class="analytics-bar-fill analytics-bar-fill-demographic" style="width: <?= View::e((string) $row['percentage']) ?>%"></span></span>
                                <span class="analytics-bar-value"><strong><?= View::e(View::formatNumber($row['total'])) ?></strong><small><?= View::e($formatPercentage($row['percentage'])) ?></small></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <details class="analytics-data-details">
                        <summary>Lihat tabel angka jenis kelamin</summary>
                        <div class="table-wrap analytics-table-wrap">
                            <table class="analytics-table analytics-demographic-table"><thead><tr><th>Jenis kelamin</th><th>Pasien</th><th>Persentase</th></tr></thead><tbody>
                            <?php foreach ($operational['demographics']['sexes'] as $row): ?>
                                <tr><td data-label="Jenis kelamin"><?= View::e($row['label']) ?></td><td data-label="Pasien"><?= View::e(View::formatNumber($row['total'])) ?></td><td data-label="Persentase"><?= View::e($formatPercentage($row['percentage'])) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody></table>
                        </div>
                    </details>
                </section>
            </div>
        </article>

        <article class="analytics-panel analytics-panel-wide" id="hasil-abnormal">
            <div class="analytics-section-heading">
                <div>
                    <p class="eyebrow">Hasil agregat</p>
                    <h2>Ringkasan hasil abnormal per parameter</h2>
                    <p class="help-text">Menggunakan nilai rujukan aktif saat ini untuk seluruh riwayat. Angka ini bukan diagnosis dan hasil tanpa acuan tidak dihitung sebagai normal.</p>
                </div>
            </div>
            <?php if ($clinical['parameters'] === []): ?>
                <div class="empty-state analytics-empty-state">
                    <strong>Belum ada hasil parameter untuk dinilai.</strong>
                    <span>Pemeriksaan tetap masuk KPI operasional meskipun belum memiliki hasil.</span>
                </div>
            <?php else: ?>
                <div class="table-wrap analytics-table-wrap">
                    <table class="analytics-table analytics-abnormal-table">
                        <thead>
                        <tr><th>Parameter</th><th>Kategori</th><th>Jumlah hasil</th><th>Rendah</th><th>Tinggi / abnormal</th><th>Belum dinilai</th><th>Persentase abnormal</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clinical['parameters'] as $row): ?>
                            <?php $highOrAbnormal = $row['high_results'] + $row['qualitative_abnormal_results']; ?>
                            <tr>
                                <td><strong><?= View::e($row['label']) ?></strong><small class="analytics-code"><?= View::e($row['code']) ?></small></td>
                                <td><?= View::e($row['panel_name']) ?></td>
                                <td><?= View::e(View::formatNumber($row['total_results'])) ?></td>
                                <td class="<?= $row['low_results'] > 0 ? 'analytics-abnormal-number' : '' ?>"><?= View::e(View::formatNumber($row['low_results'])) ?></td>
                                <td class="<?= $highOrAbnormal > 0 ? 'analytics-abnormal-number' : '' ?>"><?= View::e(View::formatNumber($highOrAbnormal)) ?></td>
                                <td><?= View::e(View::formatNumber($row['not_assessed_results'])) ?></td>
                                <td><?= View::e($formatPercentage($row['abnormal_percentage'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="analytics-scope-note">
                    <?= View::e(View::formatNumber($clinical['totals']['not_assessed_results'])) ?> hasil belum dinilai; <?= View::e(View::formatNumber($clinical['totals']['parameters_without_reference'])) ?> di antaranya tidak mempunyai nilai rujukan aktif yang cocok.
                </p>
            <?php endif; ?>
        </article>

        <article class="analytics-panel analytics-panel-wide" id="kualitas-data">
            <div class="analytics-section-heading">
                <div>
                    <p class="eyebrow">Kualitas data</p>
                    <h2>Indikator kelengkapan dan konsistensi</h2>
                    <p class="help-text">Indikator ini bersifat informatif. Perbaikan tetap dilakukan dari halaman pasien, pemeriksaan, atau nilai rujukan.</p>
                </div>
            </div>
            <div class="analytics-quality-grid">
                <article class="analytics-quality-card"><strong><?= View::e(View::formatNumber($quality['draft_examinations'])) ?></strong><span>Pemeriksaan draft</span><small>Termasuk data legacy terverifikasi bila filter status mengizinkan.</small></article>
                <article class="analytics-quality-card"><strong><?= View::e(View::formatNumber($quality['completed_without_results'])) ?></strong><span>Tersimpan/final tanpa hasil</span><small>Pemeriksaan completed atau finalized tanpa satu pun hasil terisi.</small></article>
                <article class="analytics-quality-card"><strong><?= View::e(View::formatNumber($quality['incomplete_modern_panels'])) ?></strong><span>Panel modern belum lengkap</span><small>Pasangan pemeriksaan dan panel dengan parameter aktif yang masih kosong.</small></article>
                <article class="analytics-quality-card"><strong><?= View::e(View::formatNumber($quality['parameters_without_reference'])) ?></strong><span>Hasil tanpa rujukan aktif</span><small>Tidak dihitung sebagai normal maupun abnormal.</small></article>
                <article class="analytics-quality-card"><strong><?= View::e(View::formatNumber($quality['missing_birth_date'])) ?></strong><span>Pemeriksaan tanpa tanggal lahir valid</span><small>Dihitung per pemeriksaan dalam filter.</small></article>
                <article class="analytics-quality-card"><strong><?= View::e(View::formatNumber($quality['missing_sex'])) ?></strong><span>Pemeriksaan tanpa jenis kelamin</span><small>Nilai kosong atau di luar L/P.</small></article>
                <article class="analytics-quality-card"><strong><?= View::e(View::formatNumber($quality['missing_insurance'])) ?></strong><span>Pemeriksaan tanpa penjamin</span><small>Profil asuransi pasien belum diisi.</small></article>
            </div>
        </article>
    </section>
<?php endif; ?>
