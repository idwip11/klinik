<section class="page-heading">
    <p class="eyebrow">Pemeriksaan</p>
    <h1>Buat pemeriksaan baru</h1>
    <p>Pasien: <strong><?= View::e($patient['full_name']) ?></strong> · No. RM <?= View::e($patient['medical_record_number']) ?></p>
</section>

<?php if (!empty($errors['form'])): ?><div class="notice notice-error"><?= View::e($errors['form']) ?></div><?php endif; ?>
<?php
$selectedPanelCodes = [];
foreach (is_array($input['panel_codes'] ?? null) ? $input['panel_codes'] : [] as $code) {
    if (is_string($code)) {
        $selectedPanelCodes[$code] = true;
    }
}
?>
<form method="post" action="/examinations" class="data-form narrow-form">
    <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
    <input type="hidden" name="patient_id" value="<?= View::e($patient['id']) ?>">
    <div class="form-section">
        <div class="field-grid">
            <label>Jenis pemeriksaan *
                <select name="examination_type" id="examination-type" required>
                    <?php foreach (ExaminationType::labels() as $type => $label): ?><option value="<?= View::e($type) ?>" <?= ($input['examination_type'] ?? 'mcu_general') === $type ? 'selected' : '' ?>><?= View::e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <input type="hidden" name="suggested_lab_number" id="suggested-lab-number" value="<?= View::e($input['suggested_lab_number'] ?? '') ?>">
            <label>Nomor lab *<input name="lab_number" id="lab-number" value="<?= View::e($input['lab_number'] ?? '') ?>" required maxlength="50" autofocus data-auto-suggested="<?= !empty($labNumberIsAutomatic) ? '1' : '0' ?>"></label>
            <label>Tanggal periksa *<input name="examined_on" id="examined-on" type="date" value="<?= View::e($input['examined_on'] ?? ExaminationRepository::nowInWib()->format('Y-m-d')) ?>" required></label>
            <label>Jam periksa (WIB)<input type="time" value="<?= View::e($input['examined_time'] ?? ExaminationRepository::nowInWib()->format('H:i')) ?>" readonly aria-readonly="true"></label>
            <label>Nomor surat<input name="letter_number" value="<?= View::e($input['letter_number'] ?? '') ?>" maxlength="100"></label>
            <label>Room<input name="room" value="<?= View::e($input['room'] ?? '') ?>" maxlength="100" autocomplete="off"></label>
            <label>Kondisi/catatan singkat<input name="condition_note" value="<?= View::e($input['condition_note'] ?? '') ?>" maxlength="500"></label>
            <label class="field-wide">Keperluan pemeriksaan<input name="purpose" value="<?= View::e($input['purpose'] ?? '') ?>" maxlength="255"></label>
        </div>
    </div>
    <fieldset class="panel-selection" data-panel-selection>
        <legend>Panel pemeriksaan *</legend>
        <p class="help-text">Pilih satu atau beberapa panel yang dikerjakan pada nomor lab ini. Hanya panel terpilih yang akan digunakan pada tahap input dan cetak berikutnya.</p>
        <div class="panel-choice-grid">
            <?php foreach ($panels as $panel): ?>
                <?php if ($panel['is_always_included']): ?>
                    <div class="panel-choice panel-choice-auto">
                        <strong><?= View::e($panel['name']) ?></strong>
                        <span>Selalu disertakan</span>
                    </div>
                <?php else: ?>
                    <label class="panel-choice">
                        <input type="checkbox" name="panel_codes[]" value="<?= View::e($panel['code']) ?>" <?= isset($selectedPanelCodes[$panel['code']]) ? 'checked' : '' ?>>
                        <span><strong><?= View::e($panel['name']) ?></strong><small><?= View::e($panel['description'] ?: 'Pemeriksaan laboratorium.') ?></small></span>
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <div class="form-actions"><a href="/patients">Batal</a><button type="submit">Buat pemeriksaan</button></div>
</form>
<script>
(() => {
    const type = document.getElementById('examination-type');
    const panelSelection = document.querySelector('[data-panel-selection]');
    const labNumber = document.getElementById('lab-number');
    const suggestedLabNumber = document.getElementById('suggested-lab-number');
    const examinedOn = document.getElementById('examined-on');
    if (!type || !panelSelection) return;
    const update = () => {
        const usesPanels = type.value === 'mcu_general';
        panelSelection.hidden = !usesPanels;
        panelSelection.querySelectorAll('input[type="checkbox"]').forEach((input) => { input.disabled = !usesPanels; });
    };
    type.addEventListener('change', update);
    update();

    if (!labNumber || !suggestedLabNumber || !examinedOn) return;
    let usesAutomaticLabNumber = labNumber.dataset.autoSuggested === '1';
    labNumber.addEventListener('input', () => { usesAutomaticLabNumber = false; });
    examinedOn.addEventListener('change', async () => {
        if (!usesAutomaticLabNumber || !examinedOn.value) return;
        try {
            const response = await fetch('/examinations/lab-number?examined_on=' + encodeURIComponent(examinedOn.value), { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            if (typeof data.lab_number !== 'string') return;
            labNumber.value = data.lab_number;
            suggestedLabNumber.value = data.lab_number;
        } catch (_) {
            // Nomor yang sudah tersaji tetap dapat dikoreksi manual bila permintaan saran gagal.
        }
    });
})();
</script>
