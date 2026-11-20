<dl class="patient-summary examination-patient-identity">
    <div><dt>No. RM</dt><dd><?= View::e($examination['medical_record_number']) ?></dd></div>
    <div><dt>No. Laboratorium</dt><dd><?= View::e($examination['lab_number']) ?></dd></div>
    <div><dt>Nama pasien</dt><dd><?= View::e($examination['full_name']) ?></dd></div>
    <div><dt>Jenis kelamin</dt><dd><?= View::e(['L' => 'Laki-laki', 'P' => 'Perempuan'][$examination['sex'] ?? ''] ?? '—') ?></dd></div>
    <div><dt>Umur</dt><dd><?= View::e(View::formatAge($examination['birth_date'] ?? null, $examination['examined_on'] ?? null)) ?></dd></div>
    <div><dt>Tanggal periksa</dt><dd><?= View::e(View::formatDate($examination['examined_on'])) ?></dd></div>
    <div><dt>Ruangan</dt><dd><?= View::e(($examination['room'] ?? null) ?: '—') ?></dd></div>
    <div><dt>Asuransi / penjamin</dt><dd><?= View::e(($examination['insurance_type'] ?? null) ?: '—') ?></dd></div>
    <div class="patient-summary-left-column"><dt>Alamat</dt><dd><?= View::e(($examination['address'] ?? null) ?: '—') ?></dd></div>
</dl>
