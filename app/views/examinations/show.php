<?php
$isCompleted = ($examination['status'] ?? '') === 'completed';
$isFinalized = ($examination['status'] ?? '') === 'finalized';
$typeLabel = ExaminationType::labels()[$examination['examination_type']] ?? $examination['examination_type'];
$resultDate = ($examination['result_date'] ?? null) ?: $examination['examined_on'];
?>
<section class="page-heading page-heading-inline">
    <div>
        <p class="eyebrow"><?= View::e($typeLabel) ?></p>
        <h1><?= View::e($examination['full_name']) ?></h1>
        <p>No. RM <?= View::e($examination['medical_record_number']) ?> · No. Lab <?= View::e($examination['lab_number']) ?> · <?= View::e(View::formatDate($examination['examined_on'])) ?></p>
    </div>
    <div class="button-group">
        <a class="button-link" href="/patients/<?= View::e($examination['patient_id']) ?>">← Kembali</a>
        <a href="/patients">Cari pasien lain</a>
    </div>
</section>

<?php if ($isFinalized): ?><div class="notice notice-success">Hasil pemeriksaan sudah tersimpan.</div><?php endif; ?>
<?php if (!empty($errors['form'])): ?><div class="notice notice-error"><?= View::e($errors['form']) ?></div><?php endif; ?>
<?php if (!empty($errors['date'])): ?><div class="notice notice-error"><?= View::e($errors['date']) ?></div><?php endif; ?>

<?php if (!empty($selectedPanels)): ?>
    <section class="selected-panels-summary" aria-label="Panel pemeriksaan yang dipilih">
        <strong>Panel dipilih</strong>
        <div>
            <?php foreach ($selectedPanels as $panel): ?><span class="panel-chip<?= $panel['is_auto_included'] ? ' panel-chip-auto' : '' ?>"><?= View::e($panel['name']) ?><?= $panel['is_auto_included'] ? ' · otomatis' : '' ?></span><?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="form-section document-dates-section">
    <div class="section-heading">
        <div>
            <h2>Data pemeriksaan &amp; tanggal dokumen</h2>
            <p class="field-help">Koreksi tanggal dokumen bila diperlukan. Umur pada hasil dihitung dari tanggal lahir hingga tanggal periksa.</p>
        </div>
    </div>
    <form method="post" action="/examinations/<?= View::e($examination['id']) ?>/dates" class="data-form">
        <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
        <div class="field-grid">
            <label>Tanggal periksa *<input name="examined_on" type="date" min="<?= ExaminationRepository::DATA_RETENTION_START ?>" value="<?= View::e($examination['examined_on']) ?>" required></label>
            <label>Tanggal hasil *<input name="result_date" type="date" min="<?= ExaminationRepository::DATA_RETENTION_START ?>" value="<?= View::e($resultDate) ?>" required></label>
        </div>
        <div class="form-actions"><button type="submit">Simpan data pemeriksaan</button></div>
    </form>
</section>

<form method="post" action="/examinations/<?= View::e($examination['id']) ?>/results" class="data-form">
    <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
    <?php if ($examination['examination_type'] !== 'drug_screen'): ?><p class="field-help number-format-help">Format angka: ribuan memakai titik (1.000) dan desimal memakai koma (1,5). Penulisan seperti 1.5 akan ditolak.</p><?php endif; ?>
    <fieldset>
        <?php if (!empty($panelForm)): ?>
            <?php require ROOT_PATH . '/app/views/examinations/forms/panels.php'; ?>
        <?php elseif ($examination['examination_type'] === 'drug_screen'): ?>
            <?php require ROOT_PATH . '/app/views/examinations/forms/drug.php'; ?>
        <?php elseif ($examination['examination_type'] === 'newborn_blood'): ?>
            <?php require ROOT_PATH . '/app/views/examinations/forms/blood.php'; ?>
        <?php else: ?>
            <?php require ROOT_PATH . '/app/views/examinations/forms/mcu.php'; ?>
        <?php endif; ?>
    </fieldset>
    <div class="form-actions"><button type="submit">Simpan hasil</button></div>
</form>

<?php if (!empty($panelForm)): ?>
    <section class="finalization-panel">
        <div>
            <h2>Cetak &amp; Unduh hasil</h2>
            <p>Preview format cetak A4 atau unduh hasil dalam format Excel dan PDF.</p>
            <div class="section-nav">
                <?php if ($examination['examination_type'] === 'mcu_general'): ?>
                    <a href="/examinations/<?= View::e($examination['id']) ?>/dl-dll" target="_blank" rel="noopener">Format DL/DLL</a>
                    <a href="/examinations/<?= View::e($examination['id']) ?>/dl-dll/excel">⬇ Excel DL/DLL</a>
                    <a href="/examinations/<?= View::e($examination['id']) ?>/dl-dll/pdf">⬇ PDF DL/DLL</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="button-group">
            <a class="button-link" href="/examinations/<?= View::e($examination['id']) ?>/panel-print" target="_blank" rel="noopener">Preview cetak A4</a>
            <a class="button-link button-secondary" href="/examinations/<?= View::e($examination['id']) ?>/panel-print/excel">⬇ Unduh Excel</a>
            <a class="button-link button-secondary" href="/examinations/<?= View::e($examination['id']) ?>/panel-print/pdf">⬇ Download PDF</a>
        </div>
    </section>
<?php endif; ?>

<?php if (($isCompleted || $isFinalized) && empty($panelForm)): ?>
    <section class="finalization-panel">
        <div>
            <h2>Preview &amp; Unduh hasil</h2>
            <p>Periksa format cetak sebelum mencetak atau unduh langsung dalam format Excel dan PDF.</p>
            <div class="section-nav">
                <?php if ($examination['examination_type'] === 'mcu_general'): ?>
                    <a href="/examinations/<?= View::e($examination['id']) ?>/dl-dll">DL/DLL · Darah lengkap</a>
                    <a href="/examinations/<?= View::e($examination['id']) ?>/dl-dll/excel">⬇ Excel DL/DLL</a>
                    <a href="/examinations/<?= View::e($examination['id']) ?>/dl-dll/pdf">⬇ PDF DL/DLL</a>
                <?php endif; ?>
                <?php foreach (ExaminationType::documentTypes($examination['examination_type']) as $documentType): ?>
                    <?php if ($documentType !== 'blood_general' || $examination['examination_type'] !== 'mcu_general'): ?>
                        <a href="/examinations/<?= View::e($examination['id']) ?>/preview?type=<?= View::e($documentType) ?>"><?= View::e(['blood_general' => 'Darah/umum', 'urine_complete' => 'Urine lengkap', 'newborn_blood' => 'Darah bayi', 'drug_screen' => 'Narkoba'][$documentType]) ?></a>
                        <a href="/examinations/<?= View::e($examination['id']) ?>/preview/excel?type=<?= View::e($documentType) ?>">⬇ Unduh Excel</a>
                        <a href="/examinations/<?= View::e($examination['id']) ?>/preview/pdf?type=<?= View::e($documentType) ?>">⬇ Download PDF</a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<script>
(() => {
    const plainNumber = /^\d+(?:,\d{1,6})?$/;
    const groupedNumber = /^\d{1,3}(?:\.\d{3})+(?:,\d{1,6})?$/;

    const formatNumber = (input) => {
        const raw = input.value.trim();
        if (raw === '' || (!plainNumber.test(raw) && !groupedNumber.test(raw))) {
            return;
        }
        const parts = raw.split(',');
        const integer = parts[0].replace(/\./g, '').replace(/^0+(?=\d)/, '')
            .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        input.value = integer + (parts.length > 1 ? ',' + parts[1] : '');
    };

    const prepareNumberForEditing = (input) => {
        const raw = input.value.trim();
        if (groupedNumber.test(raw)) {
            input.value = raw.replace(/\./g, '');
        }
    };

    document.querySelectorAll('[data-number-format]').forEach((input) => {
        input.addEventListener('focus', () => prepareNumberForEditing(input));
        input.addEventListener('blur', () => formatNumber(input));
        input.addEventListener('change', () => formatNumber(input));
    });
})();
</script>
<script>
(() => {
    const examinedOn = document.querySelector('input[name="examined_on"]');
    const resultDate = document.querySelector('input[name="result_date"]');
    if (!examinedOn || !resultDate) return;
    const syncMinimum = () => {
        resultDate.min = examinedOn.value || '<?= ExaminationRepository::DATA_RETENTION_START ?>';
        if (resultDate.value && examinedOn.value && resultDate.value < examinedOn.value) {
            resultDate.value = examinedOn.value;
        }
    };
    examinedOn.addEventListener('change', syncMinimum);
    syncMinimum();
})();
</script>
