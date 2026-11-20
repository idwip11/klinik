<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

function rapidAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rapidPanels(array $panels): array
{
    return array_values(array_filter($panels, static fn (array $panel): bool => $panel['code'] === 'immunoserology'));
}

function rapidFormHtml(array $panelForm): string
{
    ob_start();
    require ROOT_PATH . '/app/views/examinations/forms/panels.php';
    return (string) ob_get_clean();
}

$expected = [
    'HBSAG_RAPID' => ['Non Reaktif', 'Reaktif'],
    'HIV_RAPID' => ['Non Reaktif', 'Reaktif'],
    'SYPHILIS_RAPID' => ['Negatif', 'Positif'],
];
$panels = rapidPanels(ExaminationPanelCatalogRepository::all());
$parameters = array_column($panels[0]['parameters'], null, 'code');
$html = rapidFormHtml($panels);
foreach ($expected as $code => $options) {
    rapidAssert($parameters[$code]['result_type'] === 'select', "$code harus berupa dropdown.");
    rapidAssert($parameters[$code]['options'] === $options, "Pilihan $code tidak sesuai.");
    rapidAssert(str_contains($html, '<select name="parameter_values[' . $code . ']">'), "Dropdown $code tidak tampil.");
    foreach ($options as $option) {
        $result = ExaminationParameterResultRepository::normalize($panels, [$code => $option]);
        rapidAssert($result['values'][$code] === $option, "Pilihan $code berubah saat dinormalisasi.");
    }
    try {
        ExaminationParameterResultRepository::normalize($panels, [$code => 'Tidak Valid']);
        throw new RuntimeException("Pilihan tidak valid masih diterima oleh $code.");
    } catch (ValidationException) {
        // Expected.
    }
}
rapidAssert(!str_contains($html, 'selected'), 'Hasil kosong tidak boleh otomatis menjadi negatif/non reaktif.');
rapidAssert(ExaminationParameterResultRepository::normalize($panels, [])['rows'] === [], 'Hasil kosong harus tetap kosong.');
rapidAssert($parameters['HBSAG_ECLIA']['result_type'] === 'numeric', 'HBsAg Eclia harus tetap numerik.');

$pdo = Database::connection();
$patientId = (int) $pdo->query('SELECT id FROM patients ORDER BY id LIMIT 1')->fetchColumn();
$userId = (int) $pdo->query('SELECT id FROM users WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
rapidAssert($patientId > 0 && $userId > 0, 'Diperlukan pasien dan pengguna lokal untuk transaksi uji.');
$pdo->beginTransaction();
try {
    $pdo->prepare(
        'INSERT INTO examinations
            (patient_id, lab_number, examination_type, status, examined_on, created_by, updated_by, created_at, updated_at)
         VALUES (?, ?, "mcu_general", "draft", CURRENT_DATE(), ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    )->execute([$patientId, '__RAPID_TEST_' . bin2hex(random_bytes(6)), $userId, $userId]);
    $id = (int) $pdo->lastInsertId();
    ExaminationPanelCatalogRepository::storeSelections(
        $pdo, $id, ExaminationPanelCatalogRepository::selectionFromInput(['immunoserology'], $pdo)
    );
    $form = rapidPanels(ExaminationPanelCatalogRepository::formForExamination($id));
    foreach ([
        ['HBSAG_RAPID' => 'Non Reaktif', 'HIV_RAPID' => 'Non Reaktif', 'SYPHILIS_RAPID' => 'Negatif'],
        ['HBSAG_RAPID' => 'Reaktif', 'HIV_RAPID' => 'Reaktif', 'SYPHILIS_RAPID' => 'Positif'],
    ] as $values) {
        $normalized = ExaminationParameterResultRepository::normalize($form, $values);
        ExaminationParameterResultRepository::replace($pdo, $id, $normalized['rows']);
        $reloaded = rapidPanels(ExaminationPanelCatalogRepository::formForExamination($id));
        $actual = array_column($reloaded[0]['parameters'], 'value', 'code');
        foreach ($values as $code => $value) {
            rapidAssert($actual[$code] === $value, "Hasil $code berubah saat dibuka ulang.");
        }
        rapidAssert(substr_count(rapidFormHtml($reloaded), 'selected') === 3, 'Ketiga hasil harus terpilih saat dibuka ulang.');
        $examination = ExaminationRepository::find($id);
        $panelForm = $reloaded;
        $flags = [];
        ob_start();
        require ROOT_PATH . '/app/views/examinations/panel_print.php';
        $printHtml = (string) ob_get_clean();
        foreach ($values as $value) {
            rapidAssert(str_contains($printHtml, View::e($value)), 'Format hasil harus dipertahankan pada cetak A4.');
        }
    }

    // Simulate historic free-text results without rewriting existing patient records.
    foreach ($form[0]['parameters'] as &$parameter) {
        if (isset($expected[$parameter['code']])) {
            $parameter['value'] = 'Hasil lama ' . $parameter['code'];
        }
    }
    unset($parameter);
    $old = array_combine(array_keys($expected), array_map(static fn (string $code): string => 'Hasil lama ' . $code, array_keys($expected)));
    $normalized = ExaminationParameterResultRepository::normalize($form, $old);
    ExaminationParameterResultRepository::replace($pdo, $id, $normalized['rows']);
    $reloaded = rapidPanels(ExaminationPanelCatalogRepository::formForExamination($id));
    $html = rapidFormHtml($reloaded);
    foreach ($old as $code => $value) {
        rapidAssert(str_contains($html, 'value="' . $value . '" selected'), "Hasil lama $code harus tetap tampil.");
        rapidAssert(ExaminationParameterResultRepository::normalize($reloaded, [$code => $value])['values'][$code] === $value, "Hasil lama $code harus dapat disimpan ulang.");
    }
    try {
        ExaminationParameterResultRepository::normalize($reloaded, ['HIV_RAPID' => $old['HBSAG_RAPID']]);
        throw new RuntimeException('Hasil lama tidak boleh dipakai sebagai pilihan baru pada parameter lain.');
    } catch (ValidationException) {
        // Expected.
    }
} finally {
    $pdo->rollBack();
}

echo "Immunoserology dropdown regression: OK; transaksi uji dibatalkan.\n";
