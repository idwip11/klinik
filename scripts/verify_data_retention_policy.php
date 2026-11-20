<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

/** @param mixed $condition */
function retentionAssert(mixed $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    ExaminationRepository::suggestLabNumber('2025-12-31');
    throw new RuntimeException('Tanggal sebelum 2026 tidak ditolak.');
} catch (ValidationException $exception) {
    retentionAssert(
        ($exception->errors()['examined_on'] ?? null) === 'Tanggal periksa harus 01/01/2026 atau setelahnya.',
        'Pesan penolakan tanggal sebelum 2026 tidak sesuai.'
    );
}

$options = [
    'examination_types' => [],
    'panels' => [],
    'insurance_types' => [],
    'sexes' => [],
    'age_groups' => [],
    'statuses' => ['results' => 'Semua data tersimpan'],
];
$filters = AnalyticsRepository::normalizeFilters([
    'date_from' => '2025-12-01',
    'date_to' => '2026-01-31',
], $options);
retentionAssert($filters['date_from'] === ExaminationRepository::DATA_RETENTION_START, 'Filter analisis tidak dibatasi ke 2026.');

try {
    AnalyticsRepository::normalizeFilters([
        'date_from' => '2025-01-01',
        'date_to' => '2025-12-31',
    ], $options);
    throw new RuntimeException('Rentang analisis sebelum 2026 tidak ditolak.');
} catch (ValidationException $exception) {
    retentionAssert(isset($exception->errors()['date_to']), 'Penolakan rentang analisis sebelum 2026 tidak sesuai.');
}

$queryMethod = new ReflectionMethod(ReportRepository::class, 'query');
[$sql, $parameters] = $queryMethod->invoke(null, []);
retentionAssert(str_contains($sql, 'e.examined_on >= :retention_start'), 'Rekap belum dibatasi ke data 2026.');
retentionAssert(($parameters['retention_start'] ?? null) === ExaminationRepository::DATA_RETENTION_START, 'Parameter retensi rekap tidak sesuai.');

echo "Verifikasi kebijakan retensi data 2026 berhasil.\n";
