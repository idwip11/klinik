<?php
$sexLabels = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
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
$referenceNotApplicableCodes = ['BLOOD_GROUP', 'RHESUS', 'ANC_BLOOD_GROUP'];
$flagsByCode = [];
foreach ($flags as $flag) {
    $flagsByCode[(string) $flag['test_code']] = $flag;
}
$resultDate = ($examination['result_date'] ?? null) ?: $examination['examined_on'];
$resultDateText = View::formatLongDate($resultDate);
?>
<div class="no-print preview-toolbar">
    <a href="/patients/<?= View::e($examination['patient_id']) ?>">← Kembali ke data pasien</a>
    <div class="preview-toolbar-actions">
        <a class="button-link button-secondary" href="/examinations/<?= View::e($examination['id']) ?>/panel-print/excel">⬇ Download Excel</a>
        <a class="button-link button-secondary" href="/examinations/<?= View::e($examination['id']) ?>/panel-print/pdf">⬇ Download PDF</a>
        <form method="post" action="/examinations/<?= View::e($examination['id']) ?>/panel-print" class="inline-form">
            <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
            <button type="submit">Cetak A4</button>
        </form>
    </div>
</div>

<article class="print-sheet panel-print-sheet">
    <header class="panel-print-header" aria-hidden="true">
        <img src="/assets/header-basmalah.png" alt="">
    </header>

    <?php require ROOT_PATH . '/app/views/examinations/patient_identity.php'; ?>

    <table class="print-table panel-print-table">
        <thead>
            <tr class="laboratory-result-title"><th colspan="5">Hasil Pemeriksaan Laboratorium Klinik</th></tr>
            <tr><th>Pemeriksaan</th><th>Hasil</th><th aria-label="Simbol"></th><th>Satuan</th><th>Nilai rujukan</th></tr>
        </thead>
        <tbody>
            <?php foreach ($panelForm as $panel): ?>
                <?php $panelParameters = array_values(array_filter($panel['parameters'], static function (array $parameter): bool {
                    $value = $parameter['value'] ?? null;
                    return $value !== null && trim((string) $value) !== '';
                })); ?>
                <?php if ($panelParameters === []): continue; endif; ?>
                <tr class="panel-print-section"><th colspan="5"><?= View::e(mb_strtoupper((string) $panel['name'])) ?></th></tr>
                <?php $currentSubsection = null; ?>
                <?php foreach ($panelParameters as $parameter): ?>
                    <?php
                    $subsection = trim((string) ($parameter['subsection'] ?? ''));
                    if ($subsection !== '' && $subsection !== $currentSubsection) {
                        $currentSubsection = $subsection;
                        ?>
                        <tr class="panel-print-subsection"><th colspan="5"><?= View::e($subsection) ?></th></tr>
                        <?php
                    }
                    $flag = $flagsByCode[(string) $parameter['code']] ?? null;
                    $value = $parameter['value'];
                    $hasValue = $value !== null && trim((string) $value) !== '';
                    $reference = $flag === null ? '—' : ReferenceEvaluator::referenceTextForSex($flag['reference_text'] ?? '', $examination['sex'] ?? null);
                    if ((string) $parameter['code'] === 'HBSAG_ECLIA') {
                        $reference = "≤ 0,9: Non Reaktif\n> 1: Reaktif";
                    }
                    if (in_array((string) $parameter['code'], $referenceNotApplicableCodes, true)) {
                        $reference = '';
                    }
                    $interpretation = $flag['interpretation'] ?? null;
                    $valueText = trim((string) ($value ?? ''));
                    $referenceText = trim((string) $reference);
                    $isPositiveAgainstNegative = preg_match('/^(positif|positive|reaktif|reactive)$/iu', $valueText) === 1
                        && preg_match('/^(negatif|negative|non[ -]?reaktif)$/iu', $referenceText) === 1;
                    $isAbnormal = in_array($interpretation, ['low', 'high', 'abnormal'], true) || $isPositiveAgainstNegative;
                    $hasDirectionSymbol = $hasValue && is_string($interpretation) && array_key_exists($interpretation, $interpretationSymbols);
                    ?>
                    <tr>
                        <td><?= View::e($parameter['label']) ?></td>
                        <td class="result-value<?= $isAbnormal ? ' result-abnormal' : '' ?>"><?= View::e($hasValue ? View::formatNumber((string) $value) : '—') ?></td>
                        <td class="interpretation-<?= View::e(is_string($interpretation) ? $interpretation : 'not_assessed') ?>">
                            <?php if ($parameter['code'] === 'ANC_BLOOD_GROUP'): ?>
                            <?php elseif ($hasDirectionSymbol && $interpretationSymbols[$interpretation] !== ''): ?>
                                <span class="interpretation-marker" title="<?= View::e($interpretationLabels[$interpretation]) ?>" aria-label="<?= View::e($interpretationLabels[$interpretation]) ?>"><?= View::e($interpretationSymbols[$interpretation]) ?></span>
                            <?php elseif (!$hasDirectionSymbol): ?>
                                <?= View::e($hasValue && is_string($interpretation) ? ($interpretationLabels[$interpretation] ?? 'Belum dinilai') : ($hasValue ? 'Belum dinilai' : 'Belum diisi')) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= View::e($flag['unit'] ?? $parameter['unit'] ?? '—') ?></td>
                        <td class="reference-value"><?= View::e($reference) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="panel-print-signature">
        <div class="panel-print-signature-block">
            <div><?= View::e($resultDateText) ?></div>
            <div>Penanggung jawab</div>
            <span class="panel-print-signature-line">&nbsp;</span>
        </div>
    </div>

    <footer class="panel-print-footer" aria-hidden="true">
        <img src="/assets/footer-basmalah.png" alt="">
    </footer>
</article>

<?php if ($autoPrint ?? false): ?><script>window.addEventListener('load', function () { window.print(); });</script><?php endif; ?>
