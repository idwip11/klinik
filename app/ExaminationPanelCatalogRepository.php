<?php

declare(strict_types=1);

final class ExaminationPanelCatalogRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(bool $includeInactive = false): array
    {
        $activeClause = $includeInactive ? '' : 'WHERE p.is_active = 1';
        $parameterActiveClause = $includeInactive ? '' : ' AND tp.is_active = 1';
        $statement = Database::connection()->query(
            'SELECT p.id AS panel_id, p.code AS panel_code, p.name AS panel_name,
                    p.description, p.is_always_included, p.is_active AS panel_is_active,
                    p.display_order AS panel_display_order,
                    tp.id AS parameter_id, tp.code AS parameter_code, tp.label AS parameter_label,
                    tp.subsection, tp.result_type, tp.unit, tp.options_json, tp.legacy_field,
                    tp.is_required, tp.print_when_empty, tp.is_active AS parameter_is_active,
                    tp.display_order AS parameter_display_order
             FROM examination_panels p
             LEFT JOIN test_parameters tp ON tp.panel_id = p.id' . $parameterActiveClause . '
             ' . $activeClause . '
             ORDER BY p.display_order, p.id, tp.display_order, tp.id'
        );

        $panels = [];
        foreach ($statement->fetchAll() as $row) {
            $panelId = (int) $row['panel_id'];
            if (!isset($panels[$panelId])) {
                $panels[$panelId] = [
                    'id' => $panelId,
                    'code' => $row['panel_code'],
                    'name' => $row['panel_name'],
                    'description' => $row['description'],
                    'is_always_included' => (bool) $row['is_always_included'],
                    'is_active' => (bool) $row['panel_is_active'],
                    'display_order' => (int) $row['panel_display_order'],
                    'parameters' => [],
                ];
            }

            if ($row['parameter_id'] === null) {
                continue;
            }

            $options = null;
            if ($row['options_json'] !== null) {
                $decoded = json_decode((string) $row['options_json'], true);
                $options = is_array($decoded) ? array_values($decoded) : null;
            }

            $panels[$panelId]['parameters'][] = [
                'id' => (int) $row['parameter_id'],
                'code' => $row['parameter_code'],
                'label' => $row['parameter_label'],
                'subsection' => $row['subsection'],
                'result_type' => $row['result_type'],
                'unit' => $row['unit'],
                'options' => $options,
                'legacy_field' => $row['legacy_field'],
                'is_required' => (bool) $row['is_required'],
                'print_when_empty' => (bool) $row['print_when_empty'],
                'is_active' => (bool) $row['parameter_is_active'],
                'display_order' => (int) $row['parameter_display_order'],
            ];
        }

        return array_values($panels);
    }

    /**
     * Validates user-selected panel codes and appends every always-included panel.
     *
     * @return list<array{id:int,code:string,name:string,is_auto_included:bool}>
     */
    public static function selectionFromInput(mixed $rawCodes, ?PDO $pdo = null): array
    {
        $requestedCodes = [];
        foreach (is_array($rawCodes) ? $rawCodes : [] as $rawCode) {
            if (!is_string($rawCode)) {
                continue;
            }
            $code = trim($rawCode);
            if ($code !== '') {
                $requestedCodes[$code] = true;
            }
        }

        $pdo ??= Database::connection();
        $rows = $pdo->query(
            'SELECT id, code, name, is_always_included
             FROM examination_panels
             WHERE is_active = 1
             ORDER BY display_order, id'
        )->fetchAll();

        $availableCodes = [];
        foreach ($rows as $row) {
            $availableCodes[(string) $row['code']] = true;
        }
        $invalidCodes = array_values(array_diff(array_keys($requestedCodes), array_keys($availableCodes)));
        if ($invalidCodes !== []) {
            throw new ValidationException(['panel_codes' => 'Pilihan panel pemeriksaan tidak valid.']);
        }

        $selections = [];
        $manualCount = 0;
        foreach ($rows as $row) {
            $isAlwaysIncluded = (bool) $row['is_always_included'];
            $isRequested = isset($requestedCodes[(string) $row['code']]);
            if (!$isAlwaysIncluded && !$isRequested) {
                continue;
            }
            if (!$isAlwaysIncluded) {
                $manualCount++;
            }
            $selections[] = [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'is_auto_included' => $isAlwaysIncluded,
            ];
        }

        if ($manualCount === 0) {
            throw new ValidationException(['panel_codes' => 'Pilih minimal satu panel pemeriksaan. Imunoserologi ditambahkan otomatis.']);
        }

        return $selections;
    }

    /** @param list<array{id:int,code:string,name:string,is_auto_included:bool}> $selections */
    public static function storeSelections(PDO $pdo, int $examinationId, array $selections): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO examination_panel_selections
                (examination_id, panel_id, is_auto_included, created_at)
             VALUES
                (:examination_id, :panel_id, :is_auto_included, UTC_TIMESTAMP())'
        );
        foreach ($selections as $selection) {
            $statement->execute([
                'examination_id' => $examinationId,
                'panel_id' => $selection['id'],
                'is_auto_included' => $selection['is_auto_included'] ? 1 : 0,
            ]);
        }
    }

    /** @return list<array{code:string,name:string,is_auto_included:bool}> */
    public static function forExamination(int $examinationId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT p.code, p.name, s.is_auto_included
             FROM examination_panel_selections s
             INNER JOIN examination_panels p ON p.id = s.panel_id
             WHERE s.examination_id = :examination_id
             ORDER BY p.display_order, p.id'
        );
        $statement->execute(['examination_id' => $examinationId]);

        return array_map(
            static fn (array $row): array => [
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'is_auto_included' => (bool) $row['is_auto_included'],
            ],
            $statement->fetchAll()
        );
    }

    /** @return list<array<string,mixed>> */
    public static function formForExamination(int $examinationId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT p.id AS panel_id, p.code AS panel_code, p.name AS panel_name, p.description,
                    p.is_always_included, p.display_order AS panel_display_order,
                    tp.id AS parameter_id, tp.code AS parameter_code, tp.label AS parameter_label,
                    tp.subsection, tp.result_type, tp.unit, tp.options_json, tp.legacy_field,
                    tp.is_required, tp.print_when_empty, tp.display_order AS parameter_display_order,
                    r.value_numeric, r.value_text
             FROM examination_panel_selections s
             INNER JOIN examination_panels p ON p.id = s.panel_id AND p.is_active = 1
             INNER JOIN test_parameters tp ON tp.panel_id = p.id AND tp.is_active = 1
             LEFT JOIN examination_parameter_results r
                    ON r.examination_id = s.examination_id AND r.parameter_id = tp.id
             WHERE s.examination_id = :examination_id
             ORDER BY p.display_order, p.id, tp.display_order, tp.id'
        );
        $statement->execute(['examination_id' => $examinationId]);

        $panels = [];
        foreach ($statement->fetchAll() as $row) {
            $panelId = (int) $row['panel_id'];
            if (!isset($panels[$panelId])) {
                $panels[$panelId] = [
                    'id' => $panelId,
                    'code' => (string) $row['panel_code'],
                    'name' => (string) $row['panel_name'],
                    'description' => $row['description'],
                    'is_always_included' => (bool) $row['is_always_included'],
                    'display_order' => (int) $row['panel_display_order'],
                    'parameters' => [],
                ];
            }

            $options = null;
            if ($row['options_json'] !== null) {
                $decoded = json_decode((string) $row['options_json'], true);
                $options = is_array($decoded) ? array_values($decoded) : null;
            }
            $value = null;
            if ($row['result_type'] === 'numeric' && $row['value_numeric'] !== null) {
                $value = rtrim(rtrim((string) $row['value_numeric'], '0'), '.');
                $value = $value === '' ? '0' : $value;
            } elseif ($row['value_text'] !== null) {
                $value = (string) $row['value_text'];
            }

            $panels[$panelId]['parameters'][] = [
                'id' => (int) $row['parameter_id'],
                'code' => (string) $row['parameter_code'],
                'label' => (string) $row['parameter_label'],
                'subsection' => $row['subsection'],
                'result_type' => (string) $row['result_type'],
                'unit' => $row['unit'],
                'options' => $options,
                'legacy_field' => $row['legacy_field'],
                'is_required' => (bool) $row['is_required'],
                'print_when_empty' => (bool) $row['print_when_empty'],
                'display_order' => (int) $row['parameter_display_order'],
                'value' => $value,
            ];
        }

        return array_values($panels);
    }
}
