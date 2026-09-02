<?php
$statusLabels = ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'abnormal' => 'Abnormal', 'not_assessed' => 'Belum dinilai'];
$referenceSexLabels = ['L' => 'Laki-laki', 'P' => 'Perempuan', 'ALL' => 'Semua jenis kelamin'];
$documentLabels = ['blood_general' => 'Hasil Laboratorium Darah/Umum', 'urine_complete' => 'Hasil Urine Lengkap', 'newborn_blood' => 'Hasil Laboratorium Darah Bayi', 'drug_screen' => 'Hasil Skrining Narkoba'];
$section = ['urine_complete' => 'urine', 'drug_screen' => 'drug'][$documentType] ?? 'blood';
$visibleFlags = array_values(array_filter($flags, static fn(array $flag): bool => $flag['section'] === $section));
?>
<div class="no-print preview-toolbar">
    <a href="/examinations/<?= View::e($examination['id']) ?>">← Kembali ke pemeriksaan</a>
    <?php if (in_array($examination['status'], ['draft', 'completed', 'finalized'], true)): ?>
        <form method="post" action="/examinations/<?= View::e($examination['id']) ?>/print" class="inline-form">
            <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="document_type" value="<?= View::e($documentType) ?>">
            <button type="submit">Cetak dan catat</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($documentType === 'drug_screen'): ?>
    <?php require ROOT_PATH . '/app/views/examinations/preview_drug_screen.php'; ?>
    <?php if ($autoPrint ?? false): ?><script>window.addEventListener('load', function () { window.print(); });</script><?php endif; ?>
    <?php return; ?>
<?php endif; ?>

<article class="print-sheet">
    <header class="print-header">
        <div>
            <h1><?= View::e(Env::get('CLINIC_NAME', 'Nama Klinik')) ?></h1>
            <?php if (Env::get('CLINIC_ADDRESS')): ?><p><?= View::e(Env::get('CLINIC_ADDRESS')) ?></p><?php endif; ?>
            <?php if (Env::get('CLINIC_PHONE')): ?><p><?= View::e(Env::get('CLINIC_PHONE')) ?></p><?php endif; ?>
        </div>
        <strong><?= View::e($documentLabels[$documentType]) ?></strong>
    </header>

    <dl class="patient-summary">
        <div><dt>Nama</dt><dd><?= View::e($examination['full_name']) ?></dd></div>
        <div><dt>No. RM</dt><dd><?= View::e($examination['medical_record_number']) ?></dd></div>
        <div><dt>No. Lab</dt><dd><?= View::e($examination['lab_number']) ?></dd></div>
        <div><dt>Tanggal Periksa</dt><dd><?= View::e(View::formatDate($examination['examined_on'])) ?></dd></div>
        <div><dt>Room</dt><dd><?= View::e(($examination['room'] ?? null) ?: '—') ?></dd></div>
        <div><dt>Jenis Kelamin</dt><dd><?= View::e($examination['sex']) ?></dd></div>
        <div><dt>Tanggal Lahir</dt><dd><?= View::e(View::formatDate($examination['birth_date'])) ?></dd></div>
    </dl>

    <?php if ($documentType === 'blood_general'): ?>
        <table class="print-table vital-table">
            <thead><tr><th colspan="4">Pemeriksaan fisik</th></tr></thead>
            <tbody>
            <tr><td>Tekanan darah</td><td><?= View::e(View::formatNumber($examination['blood_pressure_systolic'] ?? '') . '/' . View::formatNumber($examination['blood_pressure_diastolic'] ?? '')) ?></td><td>Nadi</td><td><?= View::e(View::formatNumber($examination['pulse_rate'])) ?></td></tr>
            <tr><td>Tinggi / Berat</td><td><?= View::e(View::formatNumber($examination['height_cm'])) ?> / <?= View::e(View::formatNumber($examination['weight_kg'])) ?></td><td>Suhu</td><td><?= View::e(View::formatNumber($examination['temperature_c'])) ?></td></tr>
            </tbody>
        </table>
    <?php endif; ?>

    <table class="print-table">
        <thead>
            <?php if (($examination['examination_type'] ?? '') === 'mcu_general'): ?><tr class="laboratory-result-title"><th colspan="5">Hasil Pemeriksaan Laboratorium Klinik</th></tr><?php endif; ?>
            <tr><th>Parameter</th><th>Hasil</th><th>Satuan</th><th>Referensi</th><th>Keterangan</th></tr>
        </thead>
        <tbody>
        <?php if ($visibleFlags === []): ?>
            <tr><td colspan="5">Belum ada hasil pada bagian ini.</td></tr>
        <?php else: ?>
            <?php foreach ($visibleFlags as $flag): ?>
                <?php $referenceText = ReferenceEvaluator::referenceTextForSex($flag['reference_text'] ?? '—', $examination['sex'] ?? null); ?>
                <tr>
                    <td><?= View::e($flag['display_name']) ?></td>
                    <td class="result-value"><?= View::e(View::formatNumber($flag['raw_value'])) ?></td>
                    <td><?= View::e($flag['unit']) ?></td>
                    <td><?= View::e($referenceText ?: '—') ?><?php if (!empty($flag['reference_sex'])): ?><br><small>Rujukan <?= View::e($referenceSexLabels[$flag['reference_sex']] ?? $flag['reference_sex']) ?></small><?php endif; ?></td>
                    <td class="interpretation interpretation-<?= View::e($flag['interpretation']) ?>"><?= View::e($statusLabels[$flag['interpretation']] ?? $flag['interpretation']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <footer class="print-footer">
        <p>Dokumen ini menampilkan hasil dan rentang referensi yang tersimpan saat data disimpan.</p>
        <div class="signature-block"><span>Penanggung jawab</span><span class="signature-line">&nbsp;</span></div>
    </footer>
</article>

<?php if ($autoPrint ?? false): ?><script>window.addEventListener('load', function () { window.print(); });</script><?php endif; ?>
