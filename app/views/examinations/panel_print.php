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
$referenceNotApplicableCodes = ['BLOOD_GROUP', 'RHESUS'];
$flagsByCode = [];
foreach ($flags as $flag) {
    $flagsByCode[(string) $flag['test_code']] = $flag;
}
$birthText = trim(($examination['birth_place'] ?? '') . (($examination['birth_place'] ?? '') && ($examination['birth_date'] ?? '') ? ', ' : '') . View::formatDate($examination['birth_date'] ?? null));
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$today = new DateTimeImmutable('now', new DateTimeZone('Asia/Makassar'));
$todayText = $today->format('d') . ' ' . $monthNames[(int) $today->format('n')] . ' ' . $today->format('Y');
?>
<div class="no-print preview-toolbar">
    <a href="/examinations/<?= View::e($examination['id']) ?>">← Kembali ke pemeriksaan</a>
    <form method="post" action="/examinations/<?= View::e($examination['id']) ?>/panel-print" class="inline-form">
        <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
        <button type="submit">Cetak A4</button>
    </form>
</div>

<article class="print-sheet panel-print-sheet">
    <header class="panel-print-header" aria-hidden="true">
        <img src="/assets/header-basmalah.png" alt="">
    </header>

    <dl class="panel-print-patient">
        <div class="panel-print-medical-record"><dt>No. RM</dt><dd><?= View::e($examination['medical_record_number']) ?></dd></div>
        <div><dt>No. Laboratorium</dt><dd><?= View::e($examination['lab_number']) ?></dd></div>
        <div><dt>Nama pasien</dt><dd><?= View::e($examination['full_name']) ?></dd></div>
        <div><dt>Jenis kelamin</dt><dd><?= View::e($sexLabels[$examination['sex']] ?? ($examination['sex'] ?: '—')) ?></dd></div>
        <div><dt>Tempat, tanggal lahir</dt><dd><?= View::e($birthText ?: '—') ?></dd></div>
        <div><dt>Tanggal periksa</dt><dd><?= View::e(View::formatDate($examination['examined_on'])) ?></dd></div>
        <div><dt>Room</dt><dd><?= View::e(($examination['room'] ?? null) ?: '—') ?></dd></div>
        <div class="panel-print-wide"><dt>Alamat</dt><dd><?= View::e($examination['address'] ?: '—') ?></dd></div>
    </dl>

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
                            <?php if ($hasDirectionSymbol && $interpretationSymbols[$interpretation] !== ''): ?>
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
            <div><?= View::e($todayText) ?></div>
            <div>Penanggung jawab</div>
            <span class="panel-print-signature-line">&nbsp;</span>
        </div>
    </div>

    <footer class="panel-print-footer" aria-hidden="true">
        <img src="/assets/footer-basmalah.png" alt="">
    </footer>
</article>

<?php if ($autoPrint ?? false): ?><script>window.addEventListener('load', function () { window.print(); });</script><?php endif; ?>
