<?php
$sexLabels = ['L' => 'Laki-laki', 'P' => 'Perempuan'];
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
$resultDateText = View::formatLongDate($resultDate);
$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$specimenTimeText = '—';
if (!empty($examination['created_at'])) {
    try {
        $createdAt = new DateTimeImmutable((string) $examination['created_at'], new DateTimeZone('UTC'));
        $createdAt = $createdAt->setTimezone(new DateTimeZone('Asia/Jakarta'));
        $specimenTimeText = $createdAt->format('d') . ' ' . $monthNames[(int) $createdAt->format('n')] . ' ' . $createdAt->format('Y H:i') . ' WIB';
    } catch (Throwable) {
        $specimenTimeText = '—';
    }
}
$verificationUrl = PublicVerification::url($examination);
$verificationQrDataUri = $verificationUrl === null ? null : QrCodeService::dataUri($verificationUrl);
?>
<article class="print-sheet drug-print-sheet">
    <header class="drug-print-header" aria-hidden="true">
        <img src="/assets/header-basmalah.png" alt="">
    </header>

    <?php require ROOT_PATH . '/app/views/examinations/patient_identity.php'; ?>

    <table class="print-table drug-print-table">
        <thead><tr><th>Pemeriksaan</th><th>Hasil</th><th>Nilai rujukan</th></tr></thead>
        <tbody>
            <tr class="drug-print-section"><th colspan="3">IMUNOSEROLOGI</th></tr>
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
                    <td class="result-value<?= $isAbnormal ? ' result-abnormal' : '' ?>"><?= View::e($value === null || trim((string) $value) === '' ? '—' : $value) ?></td>
                    <td><?= View::e(ReferenceEvaluator::referenceTextForSex($reference, $examination['sex'] ?? null)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="drug-post-table">
        <div class="drug-specimen-time">
            <div class="drug-specimen-time-label">
                <span aria-hidden="true">◷</span>
                <strong>Waktu pengambilan spesimen:</strong>
            </div>
            <div class="drug-specimen-time-value"><?= View::e($specimenTimeText) ?></div>
        </div>

        <div class="drug-signature">
            <div class="drug-signature-block">
                <div><?= View::e($resultDateText) ?></div>
                <div>Dokter Penanggung Jawab</div>
                <span class="drug-signature-line">&nbsp;</span>
            </div>
        </div>
    </div>

    <div class="drug-page-footer">
        <div class="drug-contact" aria-label="Kontak klinik">
            <?php if ($verificationQrDataUri !== null): ?>
                <div class="drug-contact-item drug-verification-item">
                    <img class="drug-verification-qr" src="<?= View::e($verificationQrDataUri) ?>" alt="QR verifikasi hasil elektronik">
                    <span>Scan untuk<br>verifikasi elektronik</span>
                </div>
            <?php endif; ?>
            <div class="drug-contact-item">
                <span class="drug-contact-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false"><path d="M6.6 3.5 9 3l2 4.6-1.7 1.7a15.7 15.7 0 0 0 5.4 5.4l1.7-1.7 4.6 2-.5 2.4a2 2 0 0 1-2.1 1.6C11 18.5 5.5 13 4.8 5.6A2 2 0 0 1 6.6 3.5Z"/></svg>
                </span>
                <span>0851 0030 1678<br>0821 4336 3323</span>
            </div>
            <div class="drug-contact-item drug-email-item">
                <span class="drug-contact-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false"><path d="M3.5 6.5h17v11h-17zM4 7l8 6 8-6"/></svg>
                </span>
                <a href="mailto:klinikrawatinapbasmalahmedika@gmail.com">klinikrawatinapbasmalahmedika@gmail.com</a>
            </div>
        </div>

        <footer class="drug-print-footer" aria-hidden="true">
            <img src="/assets/footer-basmalah.png" alt="">
        </footer>
    </div>
</article>
