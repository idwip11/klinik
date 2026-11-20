<nav class="section-nav" aria-label="Bagian form"><a href="#vital">Tanda vital</a><a href="#blood">Darah</a><a href="#urine">Urine</a><a href="#widal">Widal</a></nav>
<section class="form-section" id="vital">
    <h2>Tanda vital dan pemeriksaan fisik</h2>
    <div class="field-grid">
        <label>Sistolik<input name="blood_pressure_systolic" type="text" inputmode="decimal" data-number-format placeholder="Contoh: 120" value="<?= View::e(View::formatNumber($examination['blood_pressure_systolic'])) ?>"></label>
        <label>Diastolik<input name="blood_pressure_diastolic" type="text" inputmode="decimal" data-number-format placeholder="Contoh: 80" value="<?= View::e(View::formatNumber($examination['blood_pressure_diastolic'])) ?>"></label>
        <label>Tinggi badan<input name="height_cm" type="text" inputmode="decimal" data-number-format placeholder="Contoh: 165" value="<?= View::e(View::formatNumber($examination['height_cm'])) ?>"></label>
        <label>Berat badan<input name="weight_kg" type="text" inputmode="decimal" data-number-format placeholder="Contoh: 60" value="<?= View::e(View::formatNumber($examination['weight_kg'])) ?>"></label>
        <label>RR<input name="respiratory_rate" type="text" inputmode="decimal" data-number-format placeholder="Contoh: 18" value="<?= View::e(View::formatNumber($examination['respiratory_rate'])) ?>"></label>
        <label>Nadi<input name="pulse_rate" type="text" inputmode="decimal" data-number-format placeholder="Contoh: 80" value="<?= View::e(View::formatNumber($examination['pulse_rate'])) ?>"></label>
        <label>Suhu<input name="temperature_c" type="text" inputmode="decimal" data-number-format placeholder="Contoh: 36,5" value="<?= View::e(View::formatNumber($examination['temperature_c'])) ?>"></label>
        <label>Buta warna<input name="color_vision" maxlength="50" placeholder="Contoh: Normal" value="<?= View::e($examination['color_vision']) ?>"></label>
        <label>Golongan darah<select name="blood_group"><option value="">Pilih</option><?php foreach (['A', 'B', 'AB', 'O'] as $value): ?><option value="<?= $value ?>" <?= ($examination['blood_group'] ?? '') === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select></label>
        <label>Rhesus<select name="rhesus"><option value="">Pilih</option><?php foreach (['+', '-'] as $value): ?><option value="<?= $value ?>" <?= ($examination['rhesus'] ?? '') === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select></label>
    </div>
</section>

<?php require ROOT_PATH . '/app/views/examinations/forms/blood.php'; ?>

<section class="form-section" id="urine">
    <h2>Urinalisis</h2>
    <div class="field-grid">
        <label>Warna urine<input name="urine_color" maxlength="50" placeholder="Contoh: Kuning Muda" value="<?= View::e($examination['urine_color']) ?>"></label>
        <label>Kejernihan<input name="urine_clarity" maxlength="50" placeholder="Contoh: Jernih" value="<?= View::e($examination['urine_clarity']) ?>"></label>
        <label>Berat jenis<input name="urine_specific_gravity" type="text" inputmode="decimal" data-number-format placeholder="Contoh: 1,015" value="<?= View::e(View::formatNumber($examination['urine_specific_gravity'])) ?>"></label>
        <label>pH<input name="urine_ph" type="text" inputmode="decimal" data-number-format placeholder="Contoh: 6,0" value="<?= View::e(View::formatNumber($examination['urine_ph'])) ?>"></label>
        <?php
        $urineFieldCodeMap = [
            'protein' => 'URINE_PROTEIN', 'glucose_reduction' => 'URINE_GLUCOSE', 'urobilinogen' => 'URINE_UROBILINOGEN',
            'bilirubin' => 'URINE_BILIRUBIN', 'occult_blood' => 'URINE_OCCULT_BLOOD', 'ketone' => 'URINE_KETONE',
            'nitrite' => 'URINE_NITRITE', 'leukocyte_esterase' => 'URINE_LEUKOCYTE_ESTERASE', 'erythrocytes' => 'URINE_ERYTHROCYTES',
            'leukocytes' => 'URINE_LEUKOCYTES', 'squamous_cells' => 'URINE_SQUAMOUS', 'casts' => 'URINE_CASTS',
            'crystals' => 'URINE_CRYSTALS', 'bacteria' => 'URINE_BACTERIA',
        ];
        $examSex = in_array($examination['sex'] ?? null, ['L', 'P'], true) ? (string) $examination['sex'] : null;
        ?>
        <?php foreach (['protein' => 'Protein urine', 'glucose_reduction' => 'Glukosa/reduksi', 'urobilinogen' => 'Urobilinogen', 'bilirubin' => 'Bilirubin', 'occult_blood' => 'Darah samar', 'ketone' => 'Keton', 'nitrite' => 'Nitrit', 'leukocyte_esterase' => 'Lekosit', 'erythrocytes' => 'Eritrosit', 'leukocytes' => 'Lekosit sedimen', 'squamous_cells' => 'Squamos', 'casts' => 'Silinder', 'crystals' => 'Kristal', 'bacteria' => 'Bakteri'] as $field => $label): ?>
            <label><?= $label ?><input name="<?= $field ?>" maxlength="100" placeholder="<?= View::e(ReferenceEvaluator::placeholderForTestCode($urineFieldCodeMap[$field] ?? strtoupper($field), $examSex) ?? '') ?>" value="<?= View::e($examination[$field]) ?>"></label>
        <?php endforeach; ?>
    </div>
</section>

<section class="form-section" id="widal">
    <h2>Widal</h2>
    <div class="field-grid">
        <?php foreach (WidalResultOptions::FIELDS as $code => $field): ?>
            <?php $currentValue = trim((string) ($examination[$field] ?? '')); ?>
            <label>Widal <?= View::e(substr($code, 6)) ?>
                <?php if ($code === 'WIDAL_O'): ?>
                <input name="widal_o" type="text" list="widal-o-options" maxlength="50"
                       placeholder="Pilih atau ketik hasil" value="<?= View::e($currentValue) ?>" autocomplete="off">
                <datalist id="widal-o-options">
                    <?php foreach (WidalResultOptions::VALUES as $option): ?>
                        <option value="<?= View::e($option) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <?php else: ?>
                <select name="<?= View::e($field) ?>">
                    <option value="">Pilih</option>
                    <?php foreach (WidalResultOptions::forValue($currentValue) as $option): ?>
                        <option value="<?= View::e($option) ?>" <?= $currentValue === $option ? 'selected' : '' ?>><?= View::e($option) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
    </div>
</section>
