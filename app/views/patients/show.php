<?php
$sexLabels = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
$typeLabels = ExaminationType::labels();
$statusLabels = ['draft' => 'Tersimpan', 'completed' => 'Tersimpan', 'finalized' => 'Tersimpan'];
$isArchived = $patient['archived_at'] !== null;
$hasExaminations = !empty($examinations);
?>
<section class="page-heading page-heading-inline">
    <div>
        <p class="eyebrow">Data pasien</p>
        <h1><?= View::e($patient['full_name']) ?></h1>
        <p>No. RM <?= View::e($patient['medical_record_number']) ?><?php if (!empty($patient['sex'])): ?> · <?= View::e($sexLabels[$patient['sex']] ?? $patient['sex']) ?><?php endif; ?></p>
    </div>
    <div class="button-group">
        <?php if (!$isArchived): ?><a class="button-link" href="/examinations/create?patient=<?= View::e($patient['id']) ?>">Pemeriksaan baru</a><?php endif; ?>
        <a href="/patients">Cari pasien lain</a>
    </div>
</section>

<?php if ($isArchived): ?><div class="notice notice-archived">Data pasien ini berasal dari fitur arsip versi lama. Hapus permanen untuk menghapus identitas dan seluruh riwayat pemeriksaannya.</div><?php endif; ?>

<section class="form-section patient-detail-card">
    <div class="section-heading-row">
        <h2>Identitas pasien</h2>
        <?php if (!$isArchived): ?><a class="button-link" href="/patients/<?= View::e($patient['id']) ?>/edit">Edit identitas</a><?php endif; ?>
    </div>
    <dl class="patient-summary patient-summary-detail">
        <div><dt>No. RM</dt><dd><?= View::e($patient['medical_record_number']) ?></dd></div>
        <div><dt>NIK</dt><dd><?= View::e($patient['national_id'] ?: '—') ?></dd></div>
        <div><dt>Jenis kelamin</dt><dd><?= View::e($sexLabels[$patient['sex']] ?? ($patient['sex'] ?: '—')) ?></dd></div>
        <div><dt>Asuransi / penjamin</dt><dd><?= View::e($patient['insurance_type'] ?: '—') ?></dd></div>
        <div><dt>No. Asuransi</dt><dd><?= View::e($patient['insurance_number'] ?: '—') ?></dd></div>
        <div><dt>Tanggal lahir</dt><dd><?= View::e(View::formatDate($patient['birth_date'])) ?></dd></div>
        <div><dt>Alamat</dt><dd><?= View::e($patient['address'] ?: '—') ?></dd></div>
        <div><dt>Telepon</dt><dd><?= View::e($patient['phone'] ?: '—') ?></dd></div>
    </dl>
</section>

<?php if ($canManagePatientRecord): ?>
    <section class="form-section patient-record-management">
        <h2>Pengelolaan data pasien</h2>
        <p class="help-text">Penghapusan permanen akan menghapus identitas pasien, seluruh riwayat pemeriksaan, hasil, dan log cetak. Tindakan ini tidak dapat dibatalkan.</p>
        <form method="post" action="/patients/<?= View::e($patient['id']) ?>/delete" class="record-action-form" onsubmit="return confirm('Hapus permanen pasien beserta seluruh riwayat pemeriksaannya? Tindakan ini tidak dapat dibatalkan.');">
            <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
            <label>Ketik No. RM untuk konfirmasi<input name="confirm_medical_record_number" required maxlength="50" autocomplete="off"></label>
            <button type="submit" class="button-danger">Hapus permanen pasien</button>
        </form>
    </section>
<?php endif; ?>

<section class="form-section">
    <div class="section-heading-row">
        <div>
            <p class="eyebrow">Riwayat</p>
            <h2>Riwayat hasil pemeriksaan</h2>
        </div>
        <div class="button-group">
            <span class="muted"><?= count($examinations) ?> pemeriksaan</span>
            <?php if ($hasExaminations): ?><a class="button-link" href="/patients/<?= View::e($patient['id']) ?>/cumulative-results">Lihat hasil kumulatif</a><?php endif; ?>
        </div>
    </div>
    <?php if (empty($examinations)): ?>
        <p class="empty-state">Belum ada pemeriksaan untuk pasien ini.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="patient-history-table">
                <thead><tr><th>Tanggal</th><th>No. Lab</th><th>Jenis</th><th>Detail pemeriksaan</th><th>Ringkasan hasil</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($examinations as $examination): ?>
                    <tr>
                        <td data-label="Tanggal"><?= View::e(View::formatDate($examination['examined_on'])) ?></td>
                        <td data-label="No. Lab"><?= View::e($examination['lab_number']) ?></td>
                        <td data-label="Jenis"><?= View::e($typeLabels[$examination['examination_type']] ?? $examination['examination_type']) ?></td>
                        <td data-label="Detail pemeriksaan">
                            <?php $hasExaminationDetails = trim((string) ($examination['room'] ?? '')) !== '' || trim((string) ($examination['condition_note'] ?? '')) !== '' || trim((string) ($examination['purpose'] ?? '')) !== ''; ?>
                            <?php if (!$hasExaminationDetails): ?>
                                <span class="patient-history-results-empty">Tidak ada detail tambahan.</span>
                            <?php else: ?>
                                <dl class="patient-history-details">
                                    <?php if (trim((string) ($examination['room'] ?? '')) !== ''): ?><div><dt>Ruangan</dt><dd><?= View::e($examination['room']) ?></dd></div><?php endif; ?>
                                    <?php if (trim((string) ($examination['condition_note'] ?? '')) !== ''): ?><div><dt>Kondisi/catatan</dt><dd><?= View::e($examination['condition_note']) ?></dd></div><?php endif; ?>
                                    <?php if (trim((string) ($examination['purpose'] ?? '')) !== ''): ?><div><dt>Keperluan</dt><dd><?= View::e($examination['purpose']) ?></dd></div><?php endif; ?>
                                </dl>
                            <?php endif; ?>
                        </td>
                        <td data-label="Ringkasan hasil">
                            <?php if (empty($examination['result_summary'])): ?>
                                <span class="patient-history-results-empty">Belum ada hasil diisi.</span>
                            <?php else: ?>
                                <ul class="patient-history-results">
                                    <?php foreach ($examination['result_summary'] as $result): ?>
                                        <li>
                                            <span class="patient-history-result-label"><?= View::e($result['label']) ?></span>
                                            <strong class="patient-history-result-value"><?= View::e($result['numeric'] ? View::formatNumber($result['value']) : $result['value']) ?></strong>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status"><span class="status status-<?= View::e($examination['status']) ?>"><?= View::e($statusLabels[$examination['status']] ?? $examination['status']) ?></span></td>
                        <td data-label="Aksi" class="action-links">
                            <?php if ($examination['examination_type'] === 'mcu_general'): ?><a href="/examinations/<?= View::e($examination['id']) ?>/dl-dll">Print Hasil</a><?php endif; ?>
                            <?php if ($examination['examination_type'] === 'newborn_blood'): ?><a href="/examinations/<?= View::e($examination['id']) ?>/preview?type=newborn_blood">Print Hasil</a><?php endif; ?>
                            <?php if ($examination['examination_type'] === 'drug_screen'): ?><a href="/examinations/<?= View::e($examination['id']) ?>/preview?type=drug_screen">Print Hasil</a><?php endif; ?>
                            <a href="/examinations/<?= View::e($examination['id']) ?>">Edit Hasil</a>
                            <?php if ($canManagePatientRecord): ?>
                                <details class="history-delete-details">
                                    <summary>Hapus pemeriksaan ini</summary>
                                    <form method="post" action="/examinations/<?= View::e($examination['id']) ?>/delete" class="record-action-form" onsubmit="return confirm('Hapus pemeriksaan ini beserta seluruh hasilnya? Pastikan nomor lab yang diketik sudah benar. Tindakan ini tidak dapat dibatalkan.');">
                                        <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
                                        <label>Ketik No. Lab<input name="confirm_lab_number" required maxlength="50" autocomplete="off" placeholder="<?= View::e((string) $examination['lab_number']) ?>"></label>
                                        <button type="submit" class="button-danger">Hapus pemeriksaan</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
