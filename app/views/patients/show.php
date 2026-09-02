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

<?php if ($isArchived): ?><div class="notice notice-archived">Data pasien ini diarsipkan. Riwayat tetap tersedia, tetapi pasien tidak tampil pada pencarian aktif dan tidak dapat dibuatkan pemeriksaan baru.</div><?php endif; ?>

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
        <?php if ($isArchived): ?>
            <p class="help-text">Pulihkan pasien agar kembali muncul di pencarian aktif dan dapat dibuatkan pemeriksaan baru.</p>
            <form method="post" action="/patients/<?= View::e($patient['id']) ?>/restore" class="form-actions form-actions-start">
                <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
                <button type="submit" class="button-secondary">Pulihkan pasien</button>
            </form>
        <?php elseif ($hasExaminations): ?>
            <p class="help-text">Pasien ini memiliki riwayat pemeriksaan sehingga tidak dapat dihapus permanen. Arsipkan untuk menyembunyikannya dari pencarian aktif tanpa menghapus riwayat.</p>
            <form method="post" action="/patients/<?= View::e($patient['id']) ?>/archive" class="record-action-form" onsubmit="return confirm('Arsipkan pasien ini? Riwayat pemeriksaan tetap tersimpan.');">
                <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
                <label>Ketikan No. RM untuk konfirmasi<input name="confirm_medical_record_number" required maxlength="50" autocomplete="off"></label>
                <button type="submit" class="button-secondary">Arsipkan pasien</button>
            </form>
        <?php else: ?>
            <p class="help-text">Pasien ini belum memiliki riwayat pemeriksaan. Penghapusan permanen tidak dapat dibatalkan.</p>
            <form method="post" action="/patients/<?= View::e($patient['id']) ?>/delete" class="record-action-form" onsubmit="return confirm('Hapus permanen pasien ini? Tindakan ini tidak dapat dibatalkan.');">
                <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
                <label>Ketikan No. RM untuk konfirmasi<input name="confirm_medical_record_number" required maxlength="50" autocomplete="off"></label>
                <button type="submit" class="button-danger">Hapus permanen pasien</button>
            </form>
        <?php endif; ?>
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
            <table>
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
                                    <?php if (trim((string) ($examination['room'] ?? '')) !== ''): ?><div><dt>Room</dt><dd><?= View::e($examination['room']) ?></dd></div><?php endif; ?>
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
                                        <li><strong><?= View::e($result['label']) ?>:</strong> <?= View::e($result['numeric'] ? View::formatNumber($result['value']) : $result['value']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status"><span class="status status-<?= View::e($examination['status']) ?>"><?= View::e($statusLabels[$examination['status']] ?? $examination['status']) ?></span></td>
                        <td data-label="Aksi" class="action-links">
                            <?php if ($examination['examination_type'] === 'mcu_general'): ?><a href="/examinations/<?= View::e($examination['id']) ?>/dl-dll">Buka DL/DLL</a><?php endif; ?>
                            <?php if ($examination['examination_type'] === 'drug_screen'): ?><a href="/examinations/<?= View::e($examination['id']) ?>/preview?type=drug_screen">Buka Skrining Narkoba</a><?php endif; ?>
                            <a href="/examinations/<?= View::e($examination['id']) ?>">Detail</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
