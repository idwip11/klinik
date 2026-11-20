<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

function panelDefaultAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Database::connection();
$panels = ExaminationPanelCatalogRepository::all();
$catalog = array_column($panels, null, 'code');
panelDefaultAssert($catalog['widal']['is_always_included'], 'Widal harus selalu disertakan.');
panelDefaultAssert(!$catalog['immunoserology']['is_always_included'], 'Imunoserologi harus opsional.');
panelDefaultAssert(!str_contains((string) $catalog['immunoserology']['description'], 'Selalu'), 'Deskripsi Imunoserologi tidak boleh menyebut selalu disertakan.');

$patient = ['id' => 1, 'full_name' => 'Pasien uji', 'medical_record_number' => 'UJI'];
$input = [];
ob_start();
require ROOT_PATH . '/app/views/examinations/create.php';
$html = (string) ob_get_clean();
panelDefaultAssert(str_contains($html, 'name="panel_codes[]" value="immunoserology"'), 'Imunoserologi harus memiliki checklist.');
panelDefaultAssert(!str_contains($html, 'name="panel_codes[]" value="widal"'), 'Widal tidak lagi menjadi checklist opsional.');

$patientId = (int) $pdo->query('SELECT id FROM patients ORDER BY id LIMIT 1')->fetchColumn();
$userId = (int) $pdo->query('SELECT id FROM users WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
panelDefaultAssert($patientId > 0 && $userId > 0, 'Diperlukan pasien dan pengguna lokal untuk transaksi uji.');
$pdo->beginTransaction();
try {
    $newExamination = static function () use ($pdo, $patientId, $userId): int {
        $pdo->prepare(
            'INSERT INTO examinations
                (patient_id, lab_number, examination_type, status, examined_on, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, "mcu_general", "draft", CURRENT_DATE(), ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        )->execute([$patientId, '__DEFAULT_TEST_' . bin2hex(random_bytes(6)), $userId, $userId]);
        return (int) $pdo->lastInsertId();
    };
    $id = $newExamination();
    ExaminationPanelCatalogRepository::storeSelections($pdo, $id, ExaminationPanelCatalogRepository::selectionFromInput(['hematology_complete'], $pdo));
    $form = ExaminationPanelCatalogRepository::formForExamination($id);
    panelDefaultAssert(array_column($form, 'code') === ['hematology_complete', 'widal'], 'Pemeriksaan baru harus memuat Widal tanpa Imunoserologi otomatis.');
    panelDefaultAssert($form[1]['is_always_included'], 'Form harus menandai Widal sebagai otomatis.');
    try {
        ExaminationParameterResultRepository::normalize($form, ['HBSAG_RAPID' => 'Reaktif']);
        throw new RuntimeException('Hasil Imunoserologi diterima tanpa panel terpilih.');
    } catch (ValidationException) {
        // Expected.
    }
    $values = ExaminationParameterResultRepository::normalize($form, ['WIDAL_O' => 'Negatif']);
    ExaminationParameterResultRepository::replace($pdo, $id, $values['rows']);
    $panelForm = ExaminationPanelCatalogRepository::formForExamination($id);
    $examination = ExaminationRepository::find($id);
    $flags = [];
    ob_start();
    require ROOT_PATH . '/app/views/examinations/panel_print.php';
    $printHtml = (string) ob_get_clean();
    panelDefaultAssert(str_contains($printHtml, 'WIDAL') && !str_contains($printHtml, 'IMUNOSEROLOGI'), 'Cetak harus mengikuti panel pemeriksaan baru.');

    $optionalId = $newExamination();
    ExaminationPanelCatalogRepository::storeSelections($pdo, $optionalId, ExaminationPanelCatalogRepository::selectionFromInput(['immunoserology'], $pdo));
    $optional = ExaminationPanelCatalogRepository::formForExamination($optionalId);
    panelDefaultAssert(array_column($optional, 'code') === ['widal', 'immunoserology'], 'Imunoserologi yang dicentang harus disertakan.');
    panelDefaultAssert(!$optional[1]['is_always_included'], 'Imunoserologi terpilih tidak boleh ditandai otomatis.');

    // Simulate a saved examination created before the default changed.
    $oldId = $newExamination();
    $oldSelections = [];
    foreach (['hematology_complete', 'immunoserology'] as $code) {
        $oldSelections[] = [
            'id' => $catalog[$code]['id'], 'code' => $code, 'name' => $catalog[$code]['name'],
            'is_auto_included' => $code === 'immunoserology',
        ];
    }
    ExaminationPanelCatalogRepository::storeSelections($pdo, $oldId, $oldSelections);
    $oldForm = ExaminationPanelCatalogRepository::formForExamination($oldId);
    panelDefaultAssert(array_column($oldForm, 'code') === ['hematology_complete', 'immunoserology'], 'Panel pemeriksaan lama tidak boleh ditambah/dihapus.');
    panelDefaultAssert($oldForm[1]['is_always_included'], 'Penanda otomatis pemeriksaan lama harus mengikuti pilihan tersimpan.');
    $normalized = ExaminationParameterResultRepository::normalize($oldForm, ['HBSAG_RAPID' => 'Non Reaktif']);
    ExaminationParameterResultRepository::replace($pdo, $oldId, $normalized['rows']);
    $oldReloaded = ExaminationPanelCatalogRepository::formForExamination($oldId);
    $oldResults = array_column($oldReloaded[1]['parameters'], 'value', 'code');
    panelDefaultAssert($oldResults['HBSAG_RAPID'] === 'Non Reaktif', 'Hasil pemeriksaan lama harus tetap dapat disimpan.');
} finally {
    $pdo->rollBack();
}

echo "Panel defaults regression: OK; transaksi uji dibatalkan.\n";
