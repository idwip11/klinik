<section class="form-section" id="blood">
    <h2><?= ($examination['examination_type'] ?? '') === 'newborn_blood' ? 'Hasil darah bayi' : 'Hematologi dan kimia darah' ?></h2>
    <div class="field-grid">
        <?php foreach (['hgb' => 'HGB', 'wbc' => 'WBC', 'plt' => 'PLT', 'rbc' => 'RBC', 'hct' => 'HCT', 'lymph_pct' => 'LYMPH%', 'mid_pct' => 'MID%', 'gran_pct' => 'GRAN%', 'mcv' => 'MCV', 'mch' => 'MCH', 'mchc' => 'MCHC', 'eosinophil' => 'Eosinofil', 'basophil' => 'Basofil', 'neutrophil' => 'Neutrofil', 'lymphocyte' => 'Limfosit', 'monocyte' => 'Monosit', 'gds' => 'GDS', 'fasting_glucose' => 'Glukosa Darah Puasa', 'glucose_2h_pp' => 'Glukosa Darah 2 Jam PP', 'urea' => 'Ureum', 'creatinine' => 'Kreatinin', 'cholesterol' => 'Kolesterol', 'uric_acid' => 'Asam urat', 'sgot' => 'SGOT', 'sgpt' => 'SGPT'] as $field => $label): ?>
            <?php if (($examination['examination_type'] ?? '') !== 'newborn_blood' || in_array($field, ['hgb', 'wbc', 'plt', 'rbc', 'hct'], true)): ?>
                <label><?= $label ?><input name="<?= $field ?>" type="text" inputmode="decimal" data-number-format value="<?= View::e(View::formatNumber($examination[$field] ?? '')) ?>"></label>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
