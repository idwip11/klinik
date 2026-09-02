<nav class="section-nav" aria-label="Bagian form"><a href="#vital">Tanda vital</a><a href="#blood">Darah</a><a href="#urine">Urine</a><a href="#widal">Widal</a></nav>
<section class="form-section" id="vital">
    <h2>Tanda vital dan pemeriksaan fisik</h2>
    <div class="field-grid">
        <label>Sistolik<input name="blood_pressure_systolic" type="text" inputmode="decimal" data-number-format value="<?= View::e(View::formatNumber($examination['blood_pressure_systolic'])) ?>"></label>
        <label>Diastolik<input name="blood_pressure_diastolic" type="text" inputmode="decimal" data-number-format value="<?= View::e(View::formatNumber($examination['blood_pressure_diastolic'])) ?>"></label>
        <label>Tinggi badan<input name="height_cm" type="text" inputmode="decimal" data-number-format value="<?= View::e(View::formatNumber($examination['height_cm'])) ?>"></label>
        <label>Berat badan<input name="weight_kg" type="text" inputmode="decimal" data-number-format value="<?= View::e(View::formatNumber($examination['weight_kg'])) ?>"></label>
        <label>RR<input name="respiratory_rate" type="text" inputmode="decimal" data-number-format value="<?= View::e(View::formatNumber($examination['respiratory_rate'])) ?>"></label>
        <label>Nadi<input name="pulse_rate" type="text" inputmode="decimal" data-number-format value="<?= View::e(View::formatNumber($examination['pulse_rate'])) ?>"></label>
        <label>Suhu<input name="temperature_c" type="text" inputmode="decimal" data-number-format value="<?= View::e(View::formatNumber($examination['temperature_c'])) ?>"></label>
        <label>Buta warna<input name="color_vision" maxlength="50" value="<?= View::e($examination['color_vision']) ?>"></label>
        <label>Golongan darah<select name="blood_group"><option value="">Pilih</option><?php foreach (['A', 'B', 'AB', 'O'] as $value): ?><option value="<?= $value ?>" <?= ($examination['blood_group'] ?? '') === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select></label>
        <label>Rhesus<select name="rhesus"><option value="">Pilih</option><?php foreach (['+', '-'] as $value): ?><option value="<?= $value ?>" <?= ($examination['rhesus'] ?? '') === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select></label>
    </div>
</section>

<?php require ROOT_PATH . '/app/views/examinations/forms/blood.php'; ?>

<section class="form-section" id="urine">
    <h2>Urinalisis</h2>
    <div class="field-grid">
        <label>Warna urine<input name="urine_color" maxlength="50" value="<?= View::e($examination['urine_color']) ?>"></label>
        <label>Kejernihan<input name="urine_clarity" maxlength="50" value="<?= View::e($examination['urine_clarity']) ?>"></label>
        <label>Berat jenis<input name="urine_specific_gravity" type="text" inputmode="decimal" data-number-format value="<?= View::e(View::formatNumber($examination['urine_specific_gravity'])) ?>"></label>
        <label>pH<input name="urine_ph" type="text" inputmode="decimal" data-number-format value="<?= View::e(View::formatNumber($examination['urine_ph'])) ?>"></label>
        <?php foreach (['protein' => 'Protein urine', 'glucose_reduction' => 'Glukosa/reduksi', 'urobilinogen' => 'Urobilinogen', 'bilirubin' => 'Bilirubin', 'occult_blood' => 'Darah samar', 'ketone' => 'Keton', 'nitrite' => 'Nitrit', 'leukocyte_esterase' => 'Lekosit', 'erythrocytes' => 'Eritrosit', 'leukocytes' => 'Lekosit sedimen', 'squamous_cells' => 'Squamos', 'casts' => 'Silinder', 'crystals' => 'Kristal', 'bacteria' => 'Bakteri'] as $field => $label): ?>
            <label><?= $label ?><input name="<?= $field ?>" maxlength="100" value="<?= View::e($examination[$field]) ?>"></label>
        <?php endforeach; ?>
    </div>
</section>

<section class="form-section" id="widal">
    <h2>Widal</h2>
    <div class="field-grid">
        <label>Widal O<input name="widal_o" maxlength="50" value="<?= View::e($examination['widal_o'] ?? '') ?>"></label>
        <label>Widal AO<input name="widal_ao" maxlength="50" value="<?= View::e($examination['widal_ao'] ?? '') ?>"></label>
        <label>Widal BO<input name="widal_bo" maxlength="50" value="<?= View::e($examination['widal_bo'] ?? '') ?>"></label>
        <label>Widal H<input name="widal_h" maxlength="50" value="<?= View::e($examination['widal_h'] ?? '') ?>"></label>
    </div>
</section>
