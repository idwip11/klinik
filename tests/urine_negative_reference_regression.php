<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
});

function urineReferenceAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Database::connection();
foreach (['URINE_GLUCOSE', 'URINE_PROTEIN', 'URINE_UROBILINOGEN', 'URINE_BILIRUBIN', 'URINE_OCCULT_BLOOD', 'URINE_KETONE'] as $code) {
    $normalValue = $code === 'URINE_UROBILINOGEN' ? 'Normal' : 'Negatif';
    foreach (['P', 'L', null] as $sex) {
        foreach ([[0, 0], [10950, 30], [36500, 100], [null, null]] as [$ageDays, $ageYears]) {
            $range = ReferenceRangeRepository::findApplicable($code, $sex, $ageDays, $ageYears);
            urineReferenceAssert($range !== false, "$code: aturan tidak tersedia.");
            urineReferenceAssert($range['sex'] === 'ALL', "$code: aturan harus universal.");
            urineReferenceAssert($range['result_kind'] === 'qualitative', "$code: aturan harus kualitatif.");
            urineReferenceAssert($range['unit'] === null, "$code: hasil kualitatif tidak bersatuan.");
            urineReferenceAssert(ReferenceEvaluator::referenceText($range) === $normalValue, "$code: rujukan harus $normalValue.");
            urineReferenceAssert(ReferenceEvaluator::placeholderForTestCode($code, $sex, $ageDays, $ageYears) === 'Contoh: ' . $normalValue, "$code: placeholder harus mengikuti aturan baru.");
            urineReferenceAssert(ReferenceEvaluator::interpretValue('qualitative', ' ' . strtolower($normalValue) . ' ', $range) === 'normal', "$code: $normalValue harus normal.");
            urineReferenceAssert(ReferenceEvaluator::interpretValue('qualitative', 'Positif', $range) === 'abnormal', "$code: Positif tidak boleh normal.");
        }
    }
    $statement = $pdo->prepare('SELECT COUNT(*) FROM reference_ranges WHERE test_code = ? AND is_active = 1 AND approval_status = "approved"');
    $statement->execute([$code]);
    urineReferenceAssert((int) $statement->fetchColumn() === 1, "$code: aturan aktif tidak boleh tumpang tindih.");
    $statement = $pdo->prepare('SELECT unit FROM test_parameters WHERE code = ?');
    $statement->execute([$code]);
    urineReferenceAssert($statement->fetchColumn() === null, "$code: satuan katalog harus sesuai hasil kualitatif.");
}

foreach (['ANC_URINE_ALBUMIN', 'ANC_URINE_REDUCTION'] as $code) {
    urineReferenceAssert(ReferenceRangeRepository::findApplicable($code, 'L', 10950, 30) === false, "$code: aturan ANC harus tetap khusus perempuan.");
    $range = ReferenceRangeRepository::findApplicable($code, 'P', 10950, 30);
    urineReferenceAssert($range !== false && $range['sex'] === 'P', "$code: aturan ANC perempuan harus tetap tersedia.");
}

echo "PASS: enam rujukan urine kualitatif untuk semua gender/usia, placeholder, penilaian, dan cakupan ANC.\n";
