<section class="page-heading page-heading-inline report-heading">
    <div>
        <p class="eyebrow">Rekap</p>
        <h1>Rekap pemeriksaan</h1>
        <p>Data ditarik langsung dari pemeriksaan dalam aplikasi, tanpa sheet laporan terpisah.</p>
    </div>
    <?php if ($canViewReportSummary): ?><a class="button-link" href="/reports/export?<?= View::e(http_build_query($filters)) ?>">Ekspor Excel</a><?php endif; ?>
</section>

<form method="get" action="/reports" class="data-form report-filter">
    <div class="field-grid">
        <label>Dari tanggal<input type="date" name="date_from" min="<?= ExaminationRepository::DATA_RETENTION_START ?>" value="<?= View::e($filters['date_from'] ?? '') ?>"></label>
        <label>Sampai tanggal<input type="date" name="date_to" min="<?= ExaminationRepository::DATA_RETENTION_START ?>" value="<?= View::e($filters['date_to'] ?? '') ?>"></label>
        <label>Jenis pemeriksaan<select name="type"><option value="">Semua</option><?php foreach (ExaminationType::labels() as $type => $label): ?><option value="<?= View::e($type) ?>" <?= ($filters['type'] ?? '') === $type ? 'selected' : '' ?>><?= View::e($label) ?></option><?php endforeach; ?></select></label>
        <label>Panel pemeriksaan<select name="panel"><option value="">Semua panel</option><?php foreach ($reportPanels as $panel): ?><option value="<?= View::e($panel['code']) ?>" <?= ($filters['panel'] ?? '') === $panel['code'] ? 'selected' : '' ?>><?= View::e($panel['name']) ?></option><?php endforeach; ?></select></label>
        <label>Jenis kelamin<select name="sex"><option value="">Semua</option><option value="P" <?= ($filters['sex'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option><option value="L" <?= ($filters['sex'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option></select></label>
        <label>Asuransi
            <select name="insurance_type">
                <option value="">Semua</option>
                <?php foreach (['Umum', 'BPJS Kesehatan', 'Pemerintah Daerah Kota'] as $insuranceType): ?>
                    <option value="<?= View::e($insuranceType) ?>" <?= ($filters['insurance_type'] ?? '') === $insuranceType ? 'selected' : '' ?>><?= View::e($insuranceType) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <input type="hidden" name="status" value="">
        <label class="field-wide">Cari<input name="q" value="<?= View::e($filters['q'] ?? '') ?>" placeholder="No. lab, no. RM, NIK, atau nama"></label>
    </div>
    <div class="form-actions"><a href="/reports">Reset</a><button type="submit">Terapkan filter</button></div>
</form>

<?php if ($canViewReportSummary): ?>
    <section class="card-grid report-summary" aria-label="Ringkasan rekap">
        <article class="card"><h2><?= View::e(View::formatNumber($summary['total'])) ?></h2><p>Total pemeriksaan</p></article>
        <article class="card"><h2><?= View::e(View::formatNumber($summary['total'])) ?></h2><p>Tersimpan</p></article>
    </section>
<?php endif; ?>

<section class="form-section report-table-section">
    <div class="section-heading-row">
        <div>
            <h2>Daftar pemeriksaan</h2>
            <p class="help-text">Geser tabel ke samping untuk melihat seluruh kolom hasil pemeriksaan.</p>
        </div>
    </div>
    <div class="table-wrap report-table-wrap" tabindex="0" aria-label="Tabel rekap pemeriksaan">
        <span class="sr-only" id="report-copy-status" aria-live="polite"></span>
        <table class="report-wide-table">
            <thead><tr>
                <th>No</th><th>No. RM</th><th>No. Lab</th><th>Nama</th><th>Asuransi</th><th>TTL</th><th>L/P</th><th>Gol. darah</th><th>Rhesus</th>
                <th>HGB</th><th>WBC</th><th>PLT</th><th>RBC</th><th>HCT</th><th>GDS</th><th>CHOL</th><th>UA</th><th>SGOT</th><th>SGPT</th><th class="report-panels-column">Panel pemeriksaan</th><th class="report-results-column">Hasil panel</th><th class="report-address">Alamat lengkap</th><th>Tanggal periksa</th><th>Jam periksa</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="25">Tidak ada data untuk filter ini.</td></tr>
            <?php else: ?>
                <?php foreach ($records as $index => $record): ?>
                    <?php
                    $birthPlace = trim((string) ($record['birth_place'] ?? ''));
                    $birthDate = View::formatDate($record['birth_date'] ?? '');
                    $birthText = implode(', ', array_filter([$birthPlace, $birthDate]));
                    $value = static fn(mixed $item): string => ($item === null || $item === '') ? '—' : View::formatNumber($item);
                    ?>
                    <tr>
                        <td><?= View::e((string) ((($page - 1) * $perPage) + $index + 1)) ?></td>
                        <td class="report-rm-cell">
                            <span><?= View::e($record['medical_record_number']) ?></span>
                            <button class="copy-value-button" type="button" data-copy-value="<?= View::e($record['medical_record_number']) ?>" aria-label="Salin No. RM <?= View::e($record['medical_record_number']) ?>" title="Salin No. RM">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="12" rx="1.5"/><path d="M16 8V5.5A1.5 1.5 0 0 0 14.5 4h-9A1.5 1.5 0 0 0 4 5.5v9A1.5 1.5 0 0 0 5.5 16H8"/></svg>
                            </button>
                        </td>
                        <td><?= View::e($record['lab_number']) ?></td>
                        <td><a class="report-patient-link" href="/patients/<?= View::e($record['patient_id']) ?>"><?= View::e($record['full_name']) ?></a></td>
                        <td><?= View::e(($record['insurance_type'] ?? '') !== '' ? $record['insurance_type'] : '—') ?></td>
                        <td><?= View::e($birthText !== '' ? $birthText : '—') ?></td>
                        <td><?= View::e(($record['sex'] ?? '') === 'L' ? 'Laki-laki' : (($record['sex'] ?? '') === 'P' ? 'Perempuan' : '—')) ?></td>
                        <td><?= View::e($value($record['blood_group'] ?? null)) ?></td>
                        <td><?= View::e($value($record['rhesus'] ?? null)) ?></td>
                        <td><?= View::e($value($record['hgb'] ?? null)) ?></td>
                        <td><?= View::e($value($record['wbc'] ?? null)) ?></td>
                        <td><?= View::e($value($record['plt'] ?? null)) ?></td>
                        <td><?= View::e($value($record['rbc'] ?? null)) ?></td>
                        <td><?= View::e($value($record['hct'] ?? null)) ?></td>
                        <td><?= View::e($value($record['gds'] ?? null)) ?></td>
                        <td><?= View::e($value($record['cholesterol'] ?? null)) ?></td>
                        <td><?= View::e($value($record['uric_acid'] ?? null)) ?></td>
                        <td><?= View::e($value($record['sgot'] ?? null)) ?></td>
                        <td><?= View::e($value($record['sgpt'] ?? null)) ?></td>
                        <td class="report-panels-column">
                            <?php foreach ($record['panels'] as $panel): ?><span class="report-panel-name"><?= View::e($panel['name']) ?><?= $panel['is_auto_included'] ? ' · otomatis' : '' ?></span><?php endforeach; ?>
                            <?php if ($record['panels'] === []): ?>—<?php endif; ?>
                        </td>
                        <td class="report-results-column">
                            <?php if ($record['panel_results'] !== []): ?>
                                <details class="report-results-details">
                                    <summary><?= count($record['panel_results']) ?> hasil · lihat rincian</summary>
                                    <dl>
                                        <?php foreach ($record['panel_results'] as $result): ?>
                                            <div><dt><?= View::e($result['label']) ?><small><?= View::e($result['panel_name']) ?></small></dt><dd><?= View::e($result['numeric'] ? View::formatNumber($result['value']) : $result['value']) ?><?= $result['unit'] ? ' ' . View::e($result['unit']) : '' ?></dd></div>
                                        <?php endforeach; ?>
                                    </dl>
                                </details>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="report-address"><?= View::e(($record['address'] ?? '') !== '' ? $record['address'] : '—') ?></td>
                        <td><?= View::e(View::formatDate($record['examined_on'])) ?></td>
                        <td><?= View::e(View::formatTime($record['examined_time'] ?? null) ?: '—') ?></td>
                        <td class="report-action-cell"><a class="report-open-button" href="/patients/<?= View::e($record['patient_id']) ?>">Lihat riwayat</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $firstRecord = $summary['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0;
    $lastRecord = min($page * $perPage, $summary['total']);
    $pageUrl = static fn(int $targetPage): string => View::e(http_build_query(array_merge($filters, ['page' => $targetPage])));
    $paginationPages = [];
    $windowStart = max(1, $page - 2);
    $windowEnd = min($totalPages, $page + 2);
    if ($windowStart > 1) {
        $paginationPages[] = 1;
        if ($windowStart > 2) {
            $paginationPages[] = '…';
        }
    }
    for ($number = $windowStart; $number <= $windowEnd; $number++) {
        $paginationPages[] = $number;
    }
    if ($windowEnd < $totalPages) {
        if ($windowEnd < $totalPages - 1) {
            $paginationPages[] = '…';
        }
        $paginationPages[] = $totalPages;
    }
    ?>
    <div class="pagination-row">
        <p class="help-text">Menampilkan <?= View::e(View::formatNumber($firstRecord)) ?>–<?= View::e(View::formatNumber($lastRecord)) ?><?= $canViewReportSummary ? ' dari ' . View::e(View::formatNumber($summary['total'])) : '' ?> pemeriksaan.</p>
        <?php if ($totalPages > 1): ?>
            <div class="pagination-controls">
                <nav class="pagination" aria-label="Navigasi halaman rekap">
                    <?php if ($page > 1): ?><a class="pagination-button" href="/reports?<?= $pageUrl($page - 1) ?>" rel="prev">‹ Sebelumnya</a><?php else: ?><span class="pagination-button is-disabled" aria-disabled="true">‹ Sebelumnya</span><?php endif; ?>
                    <div class="pagination-numbers">
                        <?php foreach ($paginationPages as $number): ?>
                            <?php if ($number === '…'): ?>
                                <span class="pagination-ellipsis" aria-hidden="true">…</span>
                            <?php else: ?>
                                <a class="pagination-number<?= $number === $page ? ' is-current' : '' ?>" href="/reports?<?= $pageUrl($number) ?>"<?= $number === $page ? ' aria-current="page"' : '' ?>><?= View::e($number) ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($page < $totalPages): ?><a class="pagination-button" href="/reports?<?= $pageUrl($page + 1) ?>" rel="next">Berikutnya ›</a><?php else: ?><span class="pagination-button is-disabled" aria-disabled="true">Berikutnya ›</span><?php endif; ?>
                </nav>
                <form method="get" action="/reports" class="pagination-jump">
                    <?php foreach ($filters as $key => $value): ?><input type="hidden" name="<?= View::e($key) ?>" value="<?= View::e($value) ?>"><?php endforeach; ?>
                    <label>Lompat ke halaman <input type="number" name="page" min="1" max="<?= View::e($totalPages) ?>" value="<?= View::e($page) ?>"></label>
                    <button type="submit">Buka</button>
                </form>
            </div>
        <?php endif; ?>
        <?php if ($canViewReportSummary): ?><p class="help-text pagination-export-note">Ekspor Excel memuat seluruh hasil sesuai filter dalam sheet Logbook, LOGBOOK MCU, dan Hasil Panel. Rincian panel mencakup ANC, Widal, dan Imunoserologi beserta nilai rujukan aktif.</p><?php endif; ?>
    </div>
</section>

<script>
document.querySelectorAll('.copy-value-button').forEach(function (button) {
    button.addEventListener('click', async function () {
        const value = this.dataset.copyValue || '';
        const status = document.getElementById('report-copy-status');
        let copied = false;

        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(value);
                copied = true;
            }
        } catch (error) {
            copied = false;
        }

        if (!copied) {
            const input = document.createElement('textarea');
            input.value = value;
            input.setAttribute('readonly', '');
            input.style.cssText = 'left:-9999px;position:fixed;top:0;';
            document.body.appendChild(input);
            input.select();
            copied = document.execCommand('copy');
            input.remove();
        }

        const message = copied ? 'No. RM ' + value + ' berhasil disalin.' : 'No. RM tidak dapat disalin. Silakan salin secara manual.';
        if (status) status.textContent = message;
        this.classList.toggle('is-copied', copied);
        this.title = copied ? 'Tersalin' : 'Salin No. RM';
        window.setTimeout(() => {
            this.classList.remove('is-copied');
            this.title = 'Salin No. RM';
        }, 1600);
    });
});
</script>
