<?php
$statusLabels = ['completed' => 'Tersimpan', 'finalized' => 'Final'];
$drugFlags = [];
foreach ($flags as $flag) {
    if (($flag['section'] ?? '') === 'drug') {
        $drugFlags[$flag['test_code']] = $flag;
    }
}
$drugRows = [
    ['code' => 'DRUG_AMPHETAMINE', 'field' => 'drug_amphetamine', 'label' => 'Amphetamine'],
    ['code' => 'DRUG_METHAMPHETAMINE', 'field' => 'drug_methamphetamine', 'label' => 'Methamphetamine'],
    ['code' => 'DRUG_MORPHINE', 'field' => 'drug_morphine', 'label' => 'Morfin'],
    ['code' => 'DRUG_MARIJUANA', 'field' => 'drug_marijuana', 'label' => 'Marijuana'],
    ['code' => 'DRUG_BENZODIAZEPINE', 'field' => 'drug_benzodiazepine', 'label' => 'Benzodiazepine'],
    ['code' => 'DRUG_COCAINE', 'field' => 'drug_cocaine', 'label' => 'Cocaine'],
];
$resultDate = ($examination['result_date'] ?? null) ?: $examination['examined_on'];
?>
<section class="verification-page">
    <p class="eyebrow">Dokumen elektronik</p>
    <h1>Verifikasi hasil skrining narkoba</h1>
    <p class="verification-intro">Halaman ini menampilkan hasil pemeriksaan yang tersimpan pada sistem Klinik Rawat Inap Basmalah Medika.</p>

    <section class="verification-card" aria-label="Informasi pemeriksaan">
        <dl class="verification-summary">
            <div><dt>No. Laboratorium</dt><dd><?= View::e($examination['lab_number']) ?></dd></div>
            <div><dt>Nama pasien</dt><dd><?= View::e($examination['full_name']) ?></dd></div>
            <div><dt>Tanggal pemeriksaan</dt><dd><?= View::e(View::formatDate($examination['examined_on'])) ?></dd></div>
            <div><dt>Tanggal hasil</dt><dd><?= View::e(View::formatDate($resultDate)) ?></dd></div>
            <div><dt>Status dokumen</dt><dd><?= View::e($statusLabels[$examination['status']] ?? 'Tersimpan') ?></dd></div>
        </dl>

        <div class="table-wrap verification-table-wrap">
            <table>
                <thead><tr><th>Pemeriksaan</th><th>Hasil</th><th>Nilai rujukan</th></tr></thead>
                <tbody>
                <?php foreach ($drugRows as $row): ?>
                    <?php
                    $flag = $drugFlags[$row['code']] ?? null;
                    $value = $flag['raw_value'] ?? $examination[$row['field']] ?? null;
                    $reference = trim((string) ($flag['reference_text'] ?? ''));
                    if ($reference === '' || $reference === 'Belum dikonfigurasi') {
                        $reference = 'Negatif';
                    }
                    $valueText = trim((string) ($value ?? ''));
                    $referenceText = trim(ReferenceEvaluator::referenceTextForSex($reference, $examination['sex'] ?? null));
                    $isPositiveAgainstNegative = preg_match('/^(positif|positive|reaktif|reactive)$/iu', $valueText) === 1
                        && preg_match('/^(negatif|negative|non[ -]?reaktif)$/iu', $referenceText) === 1;
                    $isAbnormal = ($flag['interpretation'] ?? null) === 'abnormal' || $isPositiveAgainstNegative;
                    ?>
                    <tr>
                        <td><?= View::e($row['label']) ?></td>
                        <td class="result-value<?= $isAbnormal ? ' result-abnormal' : '' ?>"><?= View::e($value === null || $valueText === '' ? '—' : $value) ?></td>
                        <td><?= View::e($referenceText) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <p class="verification-note">Halaman ini merupakan verifikasi elektronik resmi berdasarkan data pemeriksaan yang tersimpan di sistem klinik.</p>
</section>
