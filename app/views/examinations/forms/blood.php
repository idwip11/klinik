<section class="form-section" id="blood">
    <h2><?= ($examination['examination_type'] ?? '') === 'newborn_blood' ? 'Hasil darah bayi' : 'Hematologi dan kimia darah' ?></h2>
    <div class="field-grid">
        <?php
        $bloodTestCodeMap = [
            'hgb' => 'HGB', 'wbc' => 'WBC', 'plt' => 'PLT', 'rbc' => 'RBC', 'hct' => 'HCT',
            'lymph_pct' => 'LYMPH_PCT', 'mid_pct' => 'MID_PCT', 'gran_pct' => 'GRAN_PCT',
            'mcv' => 'MCV', 'mch' => 'MCH', 'mchc' => 'MCHC',
            'eosinophil' => 'EOSINOPHIL', 'basophil' => 'BASOPHIL', 'neutrophil' => 'NEUTROPHIL',
            'lymphocyte' => 'LYMPHOCYTE', 'monocyte' => 'MONOCYTE',
            'gds' => 'GDS', 'fasting_glucose' => 'FBS', 'glucose_2h_pp' => 'GLUCOSE_2H_PP',
            'urea' => 'UREA', 'creatinine' => 'CREATININE', 'cholesterol' => 'CHOL',
            'uric_acid' => 'UA', 'sgot' => 'SGOT', 'sgpt' => 'SGPT',
        ];
        $examSex = in_array($examination['sex'] ?? null, ['L', 'P'], true) ? (string) $examination['sex'] : null;
        ?>
        <?php foreach (['hgb' => 'HGB', 'hct' => 'HCT', 'rbc' => 'RBC', 'wbc' => 'WBC', 'plt' => 'PLT', 'lymph_pct' => 'LYMPH%', 'mid_pct' => 'MID%', 'gran_pct' => 'GRAN%', 'mcv' => 'MCV', 'mch' => 'MCH', 'mchc' => 'MCHC', 'eosinophil' => 'Eosinofil', 'basophil' => 'Basofil', 'neutrophil' => 'Neutrofil', 'lymphocyte' => 'Limfosit', 'monocyte' => 'Monosit', 'gds' => 'GDS', 'fasting_glucose' => 'Glukosa Darah Puasa', 'glucose_2h_pp' => 'Glukosa Darah 2 Jam PP', 'urea' => 'Ureum', 'creatinine' => 'Kreatinin', 'cholesterol' => 'Kolesterol', 'uric_acid' => 'Asam urat', 'sgot' => 'SGOT', 'sgpt' => 'SGPT'] as $field => $label): ?>
            <?php if (($examination['examination_type'] ?? '') !== 'newborn_blood' || in_array($field, ['hgb', 'hct', 'rbc', 'wbc', 'plt'], true)): ?>
                <label><?= $label ?><input name="<?= $field ?>" type="text" inputmode="decimal" data-number-format placeholder="<?= View::e(ReferenceEvaluator::placeholderForTestCode($bloodTestCodeMap[$field] ?? strtoupper($field), $examSex) ?? '') ?>" value="<?= View::e(View::formatNumber($examination[$field] ?? '')) ?>"></label>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
