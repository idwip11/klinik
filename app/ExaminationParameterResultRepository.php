<?php

declare(strict_types=1);

final class ExaminationParameterResultRepository
{
    private const HISTORICAL_TEXT_SELECT_CODES = [
        'WIDAL_AO', 'WIDAL_BO', 'WIDAL_H',
        'HBSAG_RAPID', 'HIV_RAPID', 'SYPHILIS_RAPID',
    ];

    /** @param array<string,mixed> $parameter @return list<string> */
    public static function selectOptions(array $parameter): array
    {
        $options = is_array($parameter['options'] ?? null) ? $parameter['options'] : [];
        $storedValue = trim((string) ($parameter['value'] ?? ''));
        // Preserve a former free-text result only for its own examination and parameter.
        if (in_array($parameter['code'], self::HISTORICAL_TEXT_SELECT_CODES, true)
            && $storedValue !== '' && !in_array($storedValue, $options, true)) {
            $options[] = $storedValue;
        }

        return $options;
    }

    /**
     * @param list<array<string, mixed>> $panels
     * @return array{rows:list<array<string,mixed>>,values:array<string,?string>}
     */
    public static function normalize(array $panels, mixed $rawValues): array
    {
        $submitted = is_array($rawValues) ? $rawValues : [];
        $parameters = [];
        foreach ($panels as $panel) {
            foreach ($panel['parameters'] ?? [] as $parameter) {
                $parameters[(string) $parameter['code']] = $parameter;
            }
        }

        $unknownCodes = array_values(array_diff(array_keys($submitted), array_keys($parameters)));
        if ($unknownCodes !== []) {
            throw new ValidationException(['form' => 'Terdapat parameter yang tidak termasuk panel pemeriksaan ini.']);
        }

        $rows = [];
        $values = [];
        foreach ($parameters as $code => $parameter) {
            $rawValue = $submitted[$code] ?? '';
            if (is_array($rawValue) || is_object($rawValue)) {
                throw new ValidationException(['form' => 'Format hasil ' . $parameter['label'] . ' tidak valid.']);
            }
            $value = trim((string) $rawValue);
            if ($value === '') {
                $values[$code] = null;
                continue;
            }

            $numericValue = null;
            $textValue = null;
            if ($parameter['result_type'] === 'numeric') {
                $numericValue = self::numeric($value, (string) $parameter['label']);
                $values[$code] = $numericValue;
            } elseif ($parameter['result_type'] === 'select') {
                $options = self::selectOptions($parameter);
                if (!in_array($value, $options, true)) {
                    throw new ValidationException(['form' => 'Pilihan hasil ' . $parameter['label'] . ' tidak valid.']);
                }
                $textValue = $value;
                $values[$code] = $textValue;
            } else {
                $maxLength = $code === 'WIDAL_O' ? 50 : 500;
                if (mb_strlen($value) > $maxLength) {
                    throw new ValidationException(['form' => 'Hasil ' . $parameter['label'] . ' terlalu panjang.']);
                }
                $textValue = $value;
                $values[$code] = $textValue;
            }

            $rows[] = [
                'parameter_id' => (int) $parameter['id'],
                'parameter_code_snapshot' => $code,
                'parameter_label_snapshot' => (string) $parameter['label'],
                'unit_snapshot' => $parameter['unit'] === null ? null : (string) $parameter['unit'],
                'value_numeric' => $numericValue,
                'value_text' => $textValue,
            ];
        }

        return ['rows' => $rows, 'values' => $values];
    }

    /** @param list<array<string,mixed>> $rows */
    public static function replace(PDO $pdo, int $examinationId, array $rows): void
    {
        $pdo->prepare('DELETE FROM examination_parameter_results WHERE examination_id = :examination_id')
            ->execute(['examination_id' => $examinationId]);

        $statement = $pdo->prepare(
            'INSERT INTO examination_parameter_results
                (examination_id, parameter_id, parameter_code_snapshot, parameter_label_snapshot, unit_snapshot,
                 value_numeric, value_text, created_at, updated_at)
             VALUES
                (:examination_id, :parameter_id, :parameter_code_snapshot, :parameter_label_snapshot, :unit_snapshot,
                 :value_numeric, :value_text, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        foreach ($rows as $row) {
            $statement->execute(array_merge(['examination_id' => $examinationId], $row));
        }
    }

    /** @return list<array<string,mixed>> */
    public static function forEvaluation(int $examinationId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.parameter_code_snapshot AS code, r.parameter_label_snapshot AS label,
                    r.unit_snapshot AS unit, r.value_numeric, r.value_text,
                    tp.result_type, p.code AS panel_code
             FROM examination_parameter_results r
             INNER JOIN test_parameters tp ON tp.id = r.parameter_id
             INNER JOIN examination_panels p ON p.id = tp.panel_id
             WHERE r.examination_id = :examination_id
             ORDER BY p.display_order, tp.display_order, tp.id'
        );
        $statement->execute(['examination_id' => $examinationId]);
        $results = [];
        foreach ($statement->fetchAll() as $row) {
            $value = $row['result_type'] === 'numeric' && $row['value_numeric'] !== null
                ? self::trimStoredNumeric((string) $row['value_numeric'])
                : (string) $row['value_text'];
            $row['value'] = $value;
            $results[] = $row;
        }

        return $results;
    }

    private static function numeric(string $value, string $label): string
    {
        $normalised = NumberFormat::normalize($value, $label);
        if ((float) $normalised > 99999999999999.0) {
            throw new ValidationException(['form' => 'Hasil ' . $label . ' melebihi batas penyimpanan.']);
        }

        return $normalised;
    }

    private static function trimStoredNumeric(string $value): string
    {
        $value = rtrim(rtrim($value, '0'), '.');

        return $value === '' ? '0' : $value;
    }
}
