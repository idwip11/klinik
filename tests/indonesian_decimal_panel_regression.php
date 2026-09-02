<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

function decimalRegressionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$panelForm = ExaminationPanelCatalogRepository::all();
$submittedValues = [];
foreach ($panelForm as $panel) {
    foreach ($panel['parameters'] as $parameter) {
        if ($parameter['result_type'] !== 'numeric') {
            continue;
        }
        $submittedValues[(string) $parameter['code']] = match ($parameter['code']) {
            'URINE_SG' => '1,05',
            'URINE_PH' => '4,9',
            'HBSAG_ECLIA' => '0,61',
            default => '1,5',
        };
    }
}

$normalized = ExaminationParameterResultRepository::normalize($panelForm, $submittedValues);
decimalRegressionAssert($normalized['values']['URINE_SG'] === '1.05', 'Berat Jenis dengan koma harus dinormalisasi.');
decimalRegressionAssert($normalized['values']['URINE_PH'] === '4.9', 'pH dengan koma harus dinormalisasi.');

$legacyInputMethod = new ReflectionMethod(ExaminationRepository::class, 'legacyInputFromPanelSubmission');
$legacyInput = $legacyInputMethod->invoke(null, $panelForm, $submittedValues);
decimalRegressionAssert($legacyInput['urine_specific_gravity'] === '1,05', 'Tabel kompatibilitas harus menerima Berat Jenis dalam format koma.');
decimalRegressionAssert($legacyInput['urine_ph'] === '4,9', 'Tabel kompatibilitas harus menerima pH dalam format koma.');
decimalRegressionAssert($legacyInput['hgb'] === '1,5', 'Tabel kompatibilitas harus menerima HGB dalam format koma.');
decimalRegressionAssert($legacyInput['gds'] === '1,5', 'Tabel kompatibilitas harus menerima GDS dalam format koma.');

$urineDataMethod = new ReflectionMethod(ExaminationRepository::class, 'urineData');
$urineData = $urineDataMethod->invoke(null, $legacyInput);
decimalRegressionAssert($urineData['specific_gravity'] === '1.050', 'Berat Jenis harus dapat disimpan setelah validasi format koma.');
decimalRegressionAssert($urineData['ph'] === '4.9', 'pH harus dapat disimpan setelah validasi format koma.');

$bloodDataMethod = new ReflectionMethod(ExaminationRepository::class, 'bloodData');
$bloodData = $bloodDataMethod->invoke(null, $legacyInput);
decimalRegressionAssert($bloodData['hgb'] === '1.50', 'HGB harus dapat disimpan setelah validasi format koma.');
decimalRegressionAssert($bloodData['gds'] === '1.50', 'GDS harus dapat disimpan setelah validasi format koma.');

echo "Indonesian decimal panel regression: OK\n";
