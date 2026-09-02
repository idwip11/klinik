<section class="page-heading page-heading-inline">
    <div>
        <p class="eyebrow">Administrasi</p>
        <h1>Arsip pasien</h1>
        <p>Daftar pasien yang diarsipkan. Riwayat pemeriksaan tetap tersimpan dan dapat dibuka kembali.</p>
    </div>
    <a class="button-link button-secondary" href="/patients">Kembali ke pasien aktif</a>
</section>

<form method="get" action="/patients/archived" class="search-form">
    <label class="sr-only" for="archived-patient-search">Cari pasien arsip</label>
    <input id="archived-patient-search" name="q" type="search" value="<?= View::e($query) ?>" placeholder="Nomor RM, NIK, atau nama" maxlength="150" autofocus>
    <button type="submit">Cari</button>
</form>

<?php if (empty($patients)): ?>
    <p class="empty-state"><?= $query !== '' ? 'Tidak ada pasien arsip yang cocok.' : 'Belum ada pasien yang diarsipkan.' ?></p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>No. RM</th><th>Nama</th><th>NIK</th><th>L/P</th><th>Asuransi</th><th>Diarsipkan</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($patients as $patient): ?>
                <tr>
                    <td data-label="No. RM"><?= View::e($patient['medical_record_number']) ?></td>
                    <td data-label="Nama"><?= View::e($patient['full_name']) ?></td>
                    <td data-label="NIK"><?= View::e($patient['national_id'] ?: '—') ?></td>
                    <td data-label="L/P"><?= View::e($patient['sex'] ?: '—') ?></td>
                    <td data-label="Asuransi"><?= View::e($patient['insurance_type'] ?: '—') ?></td>
                    <td data-label="Diarsipkan"><?= View::e(View::formatDate(substr((string) ($patient['archived_at'] ?? ''), 0, 10))) ?></td>
                    <td data-label="Aksi"><a href="/patients/<?= View::e($patient['id']) ?>">Lihat data &amp; pulihkan</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
