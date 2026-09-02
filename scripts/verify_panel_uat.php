<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::connection();
$failures = [];
$patientId = (int) $pdo->query('SELECT id FROM patients ORDER BY id LIMIT 1')->fetchColumn();
$userId = (int) $pdo->query('SELECT id FROM users WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
if ($patientId < 1 || $userId < 1) {
    $failures[] = 'Data sintetis membutuhkan minimal satu pasien dan satu pengguna aktif.';
} else {
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'INSERT INTO examinations
                (patient_id, lab_number, examination_type, status, examined_on, created_by, updated_by, created_at, updated_at)
             VALUES
                (:patient_id, :lab_number, "mcu_general", "draft", CURRENT_DATE(), :created_by, :updated_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute([
            'patient_id' => $patientId,
            'lab_number' => '__UAT_F7_' . bin2hex(random_bytes(6)),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        $examinationId = (int) $pdo->lastInsertId();
        $selections = ExaminationPanelCatalogRepository::selectionFromInput(['hematology_complete', 'urinalysis_complete'], $pdo);
        ExaminationPanelCatalogRepository::storeSelections($pdo, $examinationId, $selections);
        $form = ExaminationPanelCatalogRepository::formForExamination($examinationId);
        $formCodes = array_column($form, 'code');
        if ($formCodes !== ['hematology_complete', 'urinalysis_complete', 'immunoserology']) {
            $failures[] = 'UAT multi-panel tidak menambahkan Imunoserologi secara otomatis.';
        }

        $submitted = [
            'HGB' => '13,20',
            'URINE_COLOR' => 'Kuning muda',
            'BLOOD_GROUP' => 'O',
        ];
        $normalized = ExaminationParameterResultRepository::normalize($form, $submitted);
        ExaminationParameterResultRepository::replace($pdo, $examinationId, $normalized['rows']);
        $examination = ExaminationRepository::find($examinationId);
        if ($examination === false) {
            $failures[] = 'Pemeriksaan sintetis tidak dapat dibaca kembali.';
        } else {
            $flags = ReferenceEvaluator::evaluate($examination);
            $flagCodes = array_column($flags, 'test_code');
            foreach (array_keys($submitted) as $code) {
                if (!in_array($code, $flagCodes, true)) {
                    $failures[] = 'Hasil ' . $code . ' tidak masuk evaluasi panel.';
                }
            }
        }

        try {
            ExaminationParameterResultRepository::normalize($form, ['GDS' => '99']);
            $failures[] = 'Parameter dari panel yang tidak dipilih masih diterima pada UAT.';
        } catch (ValidationException) {
            // Expected.
        }

        $flags = $examination === false ? [] : FinalizationService::flags($examinationId);
        $autoPrint = false;
        ob_start();
        $panelForm = $form;
        include ROOT_PATH . '/app/views/examinations/panel_print.php';
        $html = (string) ob_get_clean();
        foreach (['header-basmalah.png', 'footer-basmalah.png', 'DL / DARAH LENGKAP', 'URINE LENGKAP', 'IMUNOSEROLOGI'] as $expected) {
            if (!str_contains($html, $expected)) {
                $failures[] = 'Template cetak panel tidak memuat: ' . $expected;
            }
        }
        if (str_contains($html, 'GULA DARAH SEWAKTU')) {
            $failures[] = 'Template cetak menampilkan parameter Kimia Klinik yang tidak dipilih.';
        }
    } finally {
        $pdo->rollBack();
    }
}

if ($failures !== []) {
    fwrite(STDERR, "UAT otomatis Fase 7 gagal:\n- " . implode("\n- ", array_unique($failures)) . "\n");
    exit(1);
}

echo "UAT otomatis Fase 7 lulus: multi-panel, Imunoserologi otomatis, pembatasan parameter, evaluasi hasil, dan render cetak A4 terverifikasi tanpa menyisakan data sintetis.\n";
