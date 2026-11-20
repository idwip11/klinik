<?php
$sexLabels = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
$referenceNotApplicableCodes = ['BLOOD_GROUP', 'RHESUS', 'ANC_BLOOD_GROUP'];
$allFlagsByCode = [];
$flagsByCode = [];
foreach ($flags as $flag) {
    $allFlagsByCode[(string) $flag['test_code']] = $flag;
    if ($flag['section'] === 'blood' && !str_starts_with((string) $flag['test_code'], 'WIDAL_')) {
        $flagsByCode[$flag['test_code']] = $flag;
    }
}
$hematologyRows = [
    ['code' => 'HGB', 'field' => 'hgb', 'label' => 'Hemoglobin (HGB)', 'unit' => 'g/dL'],
    ['code' => 'HCT', 'field' => 'hct', 'label' => 'Hematokrit (HCT)', 'unit' => '%'],
    ['code' => 'RBC', 'field' => 'rbc', 'label' => 'Eritrosit (RBC)', 'unit' => 'juta/µL'],
    ['code' => 'WBC', 'field' => 'wbc', 'label' => 'Leukosit (WBC)', 'unit' => 'sel/mm³'],
    ['code' => 'PLT', 'field' => 'plt', 'label' => 'Trombosit (PLT)', 'unit' => 'sel/mm³'],
];
$threePartDifferentialRows = [
    ['code' => 'LYMPH_PCT', 'field' => 'lymph_pct', 'label' => 'LYMPH%', 'unit' => '%'],
    ['code' => 'MID_PCT', 'field' => 'mid_pct', 'label' => 'MID%', 'unit' => '%'],
    ['code' => 'GRAN_PCT', 'field' => 'gran_pct', 'label' => 'GRAN%', 'unit' => '%'],
];
$erythrocyteIndexRows = [
    ['code' => 'MCV', 'field' => 'mcv', 'label' => 'MCV', 'unit' => 'fL'],
    ['code' => 'MCH', 'field' => 'mch', 'label' => 'MCH', 'unit' => 'pg'],
    ['code' => 'MCHC', 'field' => 'mchc', 'label' => 'MCHC', 'unit' => 'g/dL'],
];
$fivePartDifferentialRows = [
    ['code' => 'EOSINOPHIL', 'field' => 'eosinophil', 'label' => 'Eosinofil', 'unit' => '%'],
    ['code' => 'BASOPHIL', 'field' => 'basophil', 'label' => 'Basofil', 'unit' => '%'],
    ['code' => 'NEUTROPHIL', 'field' => 'neutrophil', 'label' => 'Neutrofil', 'unit' => '%'],
    ['code' => 'LYMPHOCYTE', 'field' => 'lymphocyte', 'label' => 'Limfosit', 'unit' => '%'],
    ['code' => 'MONOCYTE', 'field' => 'monocyte', 'label' => 'Monosit', 'unit' => '%'],
];
$chemistryRows = [
    ['code' => 'GDS', 'field' => 'gds', 'label' => 'Glukosa Darah Sewaktu', 'unit' => 'mg/dL'],
    ['code' => 'FBS', 'field' => 'fasting_glucose', 'label' => 'Glukosa Darah Puasa', 'unit' => 'mg/dL'],
    ['code' => 'GLUCOSE_2H_PP', 'field' => 'glucose_2h_pp', 'label' => 'Glukosa Darah 2 Jam PP', 'unit' => 'mg/dL'],
    ['code' => 'UREA', 'field' => 'urea', 'label' => 'Ureum', 'unit' => 'mg/dL'],
    ['code' => 'CREATININE', 'field' => 'creatinine', 'label' => 'Kreatinin', 'unit' => 'mg/dL'],
    ['code' => 'SGOT', 'field' => 'sgot', 'label' => 'AST (SGOT)', 'unit' => 'U/L'],
    ['code' => 'SGPT', 'field' => 'sgpt', 'label' => 'ALT (SGPT)', 'unit' => 'U/L'],
    ['code' => 'CHOL', 'field' => 'cholesterol', 'label' => 'Kolesterol', 'unit' => 'mg/dL'],
    ['code' => 'UA', 'field' => 'uric_acid', 'label' => 'Asam Urat', 'unit' => 'mg/dL'],
];

$interpretationLabels = [
    'low' => 'Rendah',
    'normal' => 'Normal',
    'high' => 'Tinggi',
    'abnormal' => 'Abnormal',
    'not_assessed' => 'Belum dinilai',
];
$interpretationSymbols = [
    'low' => '▼',
    'normal' => '',
    'high' => '▲',
];
$legacyTestCodes = [];
foreach ([$hematologyRows, $threePartDifferentialRows, $erythrocyteIndexRows, $fivePartDifferentialRows, $chemistryRows] as $rows) {
    foreach ($rows as $row) {
        $legacyTestCodes[$row['code']] = true;
    }
}
$additionalPanels = [];
foreach (($panelForm ?? []) as $panel) {
    $parameters = array_values(array_filter($panel['parameters'], static function (array $parameter) use ($legacyTestCodes): bool {
        $value = $parameter['value'] ?? null;

        return !isset($legacyTestCodes[$parameter['code']])
            && $value !== null
            && trim((string) $value) !== '';
    }));
    if ($parameters !== []) {
        $panel['parameters'] = $parameters;
        $additionalPanels[] = $panel;
    }
}
$hasRows = static function (array $rows) use ($flagsByCode, $examination): bool {
    foreach ($rows as $row) {
        $flag = $flagsByCode[$row['code']] ?? null;
        $value = $flag['raw_value'] ?? $examination[$row['field']] ?? null;
        if ($value !== null && trim((string) $value) !== '') {
            return true;
        }
    }
    return false;
};
$resultDate = ($examination['result_date'] ?? null) ?: $examination['examined_on'];
$renderRows = static function (array $rows) use ($flagsByCode, $examination, $interpretationLabels, $interpretationSymbols): void {
    foreach ($rows as $row) {
        $flag = $flagsByCode[$row['code']] ?? null;
        $value = $flag['raw_value'] ?? $examination[$row['field']] ?? null;
        if ($value === null || trim((string) $value) === '') {
            continue;
        }
        $referenceText = ReferenceEvaluator::referenceTextForSex($flag['reference_text'] ?? '—', $examination['sex'] ?? null);
        $interpretation = (string) ($flag['interpretation'] ?? 'not_assessed');
        $hasDirectionSymbol = $flag !== null && array_key_exists($interpretation, $interpretationSymbols);
        ?>
        <tr>
            <td><?= View::e($row['label']) ?></td>
            <td class="result-value <?= $flag && $interpretation !== 'normal' ? 'result-abnormal' : '' ?>"><?= View::e($value === null || trim((string) $value) === '' ? '—' : View::formatNumber($value)) ?></td>
            <td class="interpretation-<?= View::e($interpretation) ?>">
                <?php if ($hasDirectionSymbol && $interpretationSymbols[$interpretation] !== ''): ?>
                    <span class="interpretation-marker" title="<?= View::e($interpretationLabels[$interpretation]) ?>" aria-label="<?= View::e($interpretationLabels[$interpretation]) ?>"><?= View::e($interpretationSymbols[$interpretation]) ?></span>
                <?php elseif (!$hasDirectionSymbol): ?>
                    <?= View::e($flag ? ($interpretationLabels[$interpretation] ?? 'Belum dinilai') : 'Belum diisi') ?>
                <?php endif; ?>
            </td>
            <td><?= View::e($flag['unit'] ?? $row['unit']) ?></td>
            <td><?= View::e($referenceText ?: '—') ?></td>
        </tr>
        <?php
    }
};
$renderAdditionalPanelRows = static function (array $panel) use ($allFlagsByCode, $examination, $interpretationLabels, $interpretationSymbols, $referenceNotApplicableCodes): void {
    $currentSubsection = null;
    foreach ($panel['parameters'] as $parameter) {
        $subsection = trim((string) ($parameter['subsection'] ?? ''));
        if ($subsection !== '' && $subsection !== $currentSubsection) {
            $currentSubsection = $subsection;
            ?>
            <tr class="dl-dll-subsection"><th colspan="5"><?= View::e($subsection) ?></th></tr>
            <?php
        }

        $flag = $allFlagsByCode[(string) $parameter['code']] ?? null;
        $value = (string) $parameter['value'];
        $interpretation = (string) ($flag['interpretation'] ?? 'not_assessed');
        $hasDirectionSymbol = $flag !== null && array_key_exists($interpretation, $interpretationSymbols);
        $referenceText = $flag === null
            ? '—'
            : ReferenceEvaluator::referenceTextForSex($flag['reference_text'] ?? '', $examination['sex'] ?? null);
        if ((string) $parameter['code'] === 'HBSAG_ECLIA') {
            $referenceText = "≤ 0,9: Non Reaktif\n> 1: Reaktif";
        }
        if (in_array((string) $parameter['code'], $referenceNotApplicableCodes, true)) {
            $referenceText = '';
        }
        $isAbnormal = in_array($interpretation, ['low', 'high', 'abnormal'], true);
        ?>
        <tr>
            <td><?= View::e($parameter['label']) ?></td>
            <td class="result-value<?= $isAbnormal ? ' result-abnormal' : '' ?>"><?= View::e($parameter['result_type'] === 'numeric' ? View::formatNumber($value) : $value) ?></td>
            <td class="interpretation-<?= View::e($interpretation) ?>">
                <?php if ($hasDirectionSymbol && $interpretationSymbols[$interpretation] !== ''): ?>
                    <span class="interpretation-marker" title="<?= View::e($interpretationLabels[$interpretation]) ?>" aria-label="<?= View::e($interpretationLabels[$interpretation]) ?>"><?= View::e($interpretationSymbols[$interpretation]) ?></span>
                <?php endif; ?>
            </td>
            <td><?= View::e($flag['unit'] ?? $parameter['unit'] ?? '—') ?></td>
            <td class="reference-value"><?= View::e($referenceText) ?></td>
        </tr>
        <?php
    }
};
?>
<div class="no-print preview-toolbar">
    <a href="/patients/<?= View::e($examination['patient_id']) ?>">← Kembali ke data pasien</a>
    <div class="preview-toolbar-actions">
        <a class="button-link button-secondary" href="/examinations/<?= View::e($examination['id']) ?>/dl-dll/excel">⬇ Download Excel</a>
        <a class="button-link button-secondary" href="/examinations/<?= View::e($examination['id']) ?>/dl-dll/pdf">⬇ Download PDF</a>
        <form method="post" action="/examinations/<?= View::e($examination['id']) ?>/dl-dll/print" class="inline-form">
            <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
            <button type="submit">Cetak A4</button>
        </form>
    </div>
</div>

<div class="dl-dll-sheets">
<article class="print-sheet dl-dll-sheet">
    <header class="dl-dll-header">
        <img class="dl-dll-header-image" src="/assets/header-basmalah.png" alt="Klinik Rawat Inap Basmalah Medika — Laboratorium Klinik">
    </header>

    <?php require ROOT_PATH . '/app/views/examinations/patient_identity.php'; ?>

    <table class="print-table dl-dll-table">
        <thead>
            <tr class="laboratory-result-title"><th colspan="5">Hasil Pemeriksaan Laboratorium Klinik</th></tr>
            <tr><th>Pemeriksaan</th><th>Hasil</th><th aria-label="Simbol"></th><th>Satuan</th><th>Nilai rujukan</th></tr>
        </thead>
        <tbody>
        <?php if ($hasRows($hematologyRows)): ?>
            <tr class="dl-dll-section"><th colspan="5">HEMATOLOGI</th></tr>
            <?php $renderRows($hematologyRows); ?>
        <?php endif; ?>
        <?php if ($hasRows($threePartDifferentialRows)): ?>
            <tr class="dl-dll-section"><th colspan="5">HITUNG JENIS LEUKOSIT (3 BAGIAN)</th></tr>
            <?php $renderRows($threePartDifferentialRows); ?>
        <?php endif; ?>
        <?php if ($hasRows($erythrocyteIndexRows)): ?>
            <tr class="dl-dll-section"><th colspan="5">INDEKS ERITROSIT</th></tr>
            <?php $renderRows($erythrocyteIndexRows); ?>
        <?php endif; ?>
        <?php if ($hasRows($fivePartDifferentialRows)): ?>
            <tr class="dl-dll-section"><th colspan="5">HITUNG JENIS LEUKOSIT (5 BAGIAN)</th></tr>
            <?php $renderRows($fivePartDifferentialRows); ?>
        <?php endif; ?>
        <?php if ($hasRows($chemistryRows)): ?>
            <tr class="dl-dll-section"><th colspan="5">KIMIA KLINIK</th></tr>
            <?php $renderRows($chemistryRows); ?>
        <?php endif; ?>
        <?php foreach ($additionalPanels as $panel): ?>
            <tr class="dl-dll-section"><th colspan="5"><?= View::e(mb_strtoupper((string) $panel['name'])) ?></th></tr>
            <?php $renderAdditionalPanelRows($panel); ?>
        <?php endforeach; ?>
        </tbody>
    </table>

    <footer class="print-footer dl-dll-footer">
        <p><em>*Kesimpulan di atas ini hanya berdasarkan dari pemeriksaan laboratorium pada saat ini dan dari keterangan klinis yang dicantumkan.</em></p>
        <div class="signature-block"><span>Malang, <?= View::e(View::formatLongDate($resultDate)) ?></span><span class="signature-line">&nbsp;</span></div>
    </footer>
</article>
</div>

<?php if ($autoPrint ?? false): ?><script>window.addEventListener('load', function () { window.print(); });</script><?php endif; ?>
