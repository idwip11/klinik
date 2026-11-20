<?php

declare(strict_types=1);

final class ReportRepository
{
    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public static function search(array $filters, int $limit = 15, int $offset = 0): array
    {
        [$sql, $parameters] = self::query($filters);
        $limit = max(1, min(1000, $limit));
        $offset = max(0, $offset);
        $statement = Database::connection()->prepare($sql . ' ORDER BY e.examined_on DESC, e.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $statement->execute($parameters);

        return self::withPanelResults($statement->fetchAll());
    }

    /** @param array<string, mixed> $filters @return array{total: int, draft: int, completed: int, finalized: int} */
    public static function summary(array $filters): array
    {
        [$sql, $parameters] = self::query($filters, 'COUNT(*) AS total, SUM(e.status = "draft") AS draft, SUM(e.status = "completed") AS completed, SUM(e.status = "finalized") AS finalized');
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        $summary = $statement->fetch() ?: [];

        return [
            'total' => (int) ($summary['total'] ?? 0),
            'draft' => (int) ($summary['draft'] ?? 0),
            'completed' => (int) ($summary['completed'] ?? 0),
            'finalized' => (int) ($summary['finalized'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $filters */
    public static function exportCsv(array $filters): void
    {
        [$sql, $parameters] = self::query($filters);
        $statement = Database::connection()->prepare($sql . ' ORDER BY e.examined_on DESC, e.id DESC');
        $statement->execute($parameters);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="rekap-pemeriksaan-' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['No. Lab', 'Tanggal Periksa', 'Jenis Pemeriksaan', 'Status', 'No. RM', 'Nama', 'Asuransi', 'NIK', 'Jenis Kelamin', 'Nomor Surat', 'Panel pemeriksaan', 'Hasil panel'], ',', '"', '');
        foreach (self::withPanelResults($statement->fetchAll()) as $row) {
            fputcsv($output, array_map([self::class, 'safeCsv'], [
                $row['lab_number'], $row['examined_on'], self::typeLabel((string) $row['examination_type']), self::statusLabel((string) $row['status']),
                $row['medical_record_number'], $row['full_name'], $row['insurance_type'], $row['national_id'], $row['sex'], $row['letter_number'],
                implode('; ', array_column($row['panels'], 'name')),
                implode('; ', array_map(static fn (array $result): string => $result['panel_name'] . ' / ' . $result['label'] . ': ' . ($result['numeric'] ? View::formatNumber($result['value']) : $result['value']), $row['panel_results'])),
            ]), ',', '"', '');
        }
        fclose($output);
    }

    public static function exportXlsx(array $filters): never
    {
        $select = 'e.id, e.lab_number, e.examination_type, e.status, e.examined_on, e.examined_time, e.condition_note, e.purpose, e.letter_number,
                   p.medical_record_number, p.national_id, p.full_name, p.birth_place, p.birth_date, p.sex, p.address, p.phone, p.occupation, p.insurance_type,
                   v.blood_pressure_systolic, v.blood_pressure_diastolic, v.height_cm, v.weight_kg, v.respiratory_rate, v.color_vision, v.pulse_rate, v.blood_group, v.rhesus, v.temperature_c,
                   b.hgb, b.wbc, b.plt, b.rbc, b.hct, b.lymph_pct, b.mid_pct, b.gran_pct, b.mcv, b.mch, b.mchc, b.eosinophil, b.basophil, b.neutrophil, b.lymphocyte, b.monocyte,
                   b.gds, b.fasting_glucose, b.glucose_2h_pp, b.urea, b.creatinine, b.cholesterol, b.uric_acid, b.sgot, b.sgpt,
                   u.color AS urine_color, u.clarity AS urine_clarity, u.specific_gravity AS urine_specific_gravity, u.ph AS urine_ph,
                   u.protein AS urine_protein, u.glucose_reduction AS urine_glucose_reduction, u.urobilinogen AS urine_urobilinogen, u.bilirubin AS urine_bilirubin,
                   u.occult_blood AS urine_occult_blood, u.ketone AS urine_ketone, u.nitrite AS urine_nitrite, u.leukocyte_esterase AS urine_leukocyte_esterase,
                   u.erythrocytes AS urine_erythrocytes, u.leukocytes AS urine_leukocytes, u.squamous_cells AS urine_squamous_cells, u.casts AS urine_casts, u.crystals AS urine_crystals, u.bacteria AS urine_bacteria,
                   d.amphetamine AS drug_amphetamine, d.marijuana AS drug_marijuana, d.morphine AS drug_morphine, d.benzodiazepine AS drug_benzodiazepine, d.methamphetamine AS drug_methamphetamine, d.cocaine AS drug_cocaine,
                   w.widal_o, w.widal_ao, w.widal_bo, w.widal_h';
        $joins = ' LEFT JOIN vital_results v ON v.examination_id = e.id
                   LEFT JOIN blood_results b ON b.examination_id = e.id
                   LEFT JOIN urine_results u ON u.examination_id = e.id
                   LEFT JOIN drug_results d ON d.examination_id = e.id
                   LEFT JOIN widal_results w ON w.examination_id = e.id';
        [$sql, $parameters] = self::query($filters, $select, $joins);
        $statement = Database::connection()->prepare($sql . ' ORDER BY e.examined_on DESC, e.id DESC');
        $statement->execute($parameters);
        ReportExcelExporter::download(self::withPanelResults($statement->fetchAll()));
    }

    /** @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
    private static function withPanelResults(array $records): array
    {
        if ($records === []) return [];
        $pdo = Database::connection();
        $ids = implode(', ', array_map(static fn (array $record): int => (int) $record['id'], $records));
        $selected = [];
        foreach ($pdo->query(
            'SELECT s.examination_id, p.code, p.name, s.is_auto_included
             FROM examination_panel_selections s
             INNER JOIN examination_panels p ON p.id = s.panel_id
             WHERE s.examination_id IN (' . $ids . ') ORDER BY p.display_order, p.id'
        ) as $row) {
            $selected[(int) $row['examination_id']][$row['code']] = [
                'code' => $row['code'], 'name' => $row['name'], 'is_auto_included' => (bool) $row['is_auto_included'],
            ];
        }
        $results = [];
        $metadata = [];
        foreach ($pdo->query(
            'SELECT r.examination_id, r.parameter_code_snapshot AS code, r.parameter_label_snapshot AS label,
                    r.value_numeric, r.value_text, r.unit_snapshot AS unit, p.code AS panel_code, p.name AS panel_name
             FROM examination_parameter_results r
             LEFT JOIN test_parameters tp ON tp.id = r.parameter_id
             LEFT JOIN examination_panels p ON p.id = tp.panel_id
             WHERE r.examination_id IN (' . $ids . ')
               AND (r.value_numeric IS NOT NULL OR NULLIF(TRIM(r.value_text), "") IS NOT NULL)
             ORDER BY p.display_order, tp.display_order, r.id'
        ) as $row) {
            $id = (int) $row['examination_id'];
            $code = (string) $row['code'];
            $numeric = $row['value_numeric'] !== null;
            $results[$id][$code] = [
                'value' => trim((string) ($numeric ? $row['value_numeric'] : $row['value_text'])),
                'numeric' => $numeric, 'unit' => $row['unit'],
            ];
            $metadata[$id][$code] = ['code' => $code, 'label' => $row['label'], 'panel_code' => $row['panel_code'] ?? 'other', 'panel_name' => $row['panel_name'] ?? 'Pemeriksaan lainnya'];
        }
        // Older Widal results may only exist in the compatibility table.
        foreach ($records as $record) {
            $id = (int) $record['id'];
            foreach (WidalResultOptions::FIELDS as $code => $field) {
                if (isset($results[$id][$code]) || trim((string) ($record[$field] ?? '')) === '') continue;
                $results[$id][$code] = ['value' => trim((string) $record[$field]), 'numeric' => false, 'unit' => null];
                $metadata[$id][$code] = ['code' => $code, 'label' => 'Salmonella typhi ' . substr($code, 6), 'panel_code' => 'widal', 'panel_name' => 'Widal / Pemeriksaan Terkait Demam Tifoid'];
            }
        }
        $results = HistoricalReferenceEvaluator::annotatePopulation($pdo, $records, $results);
        foreach ($records as &$record) {
            $id = (int) $record['id'];
            $record['panel_results'] = [];
            foreach ($results[$id] ?? [] as $code => $result) {
                $info = $metadata[$id][$code];
                $record['panel_results'][] = array_merge($info, $result);
                $selected[$id][$info['panel_code']] ??= ['code' => $info['panel_code'], 'name' => $info['panel_name'], 'is_auto_included' => false];
            }
            $record['panels'] = array_values($selected[$id] ?? []);
            // Include the ANC measurements in the familiar summary columns as well.
            if (($record['hgb'] ?? null) === null && isset($results[$id]['ANC_HGB'])) {
                $record['hgb'] = $results[$id]['ANC_HGB']['value'];
            }
            if (($record['gds'] ?? null) === null && isset($results[$id]['ANC_GDS'])) {
                $record['gds'] = $results[$id]['ANC_GDS']['value'];
            }
            if (empty($record['blood_group']) && isset($results[$id]['ANC_BLOOD_GROUP'])
                && preg_match('/^(A|B|AB|O) \/ Rh \(([+-])\)$/', $results[$id]['ANC_BLOOD_GROUP']['value'], $match)) {
                $record['blood_group'] = $match[1];
                $record['rhesus'] = $match[2];
            }
        }
        unset($record);
        return $records;
    }

    /** @param array<string, mixed> $filters @return array{0: string, 1: array<string, mixed>} */
    private static function query(
        array $filters,
        string $select = 'e.id, e.patient_id, e.lab_number, e.examination_type, e.status, e.examined_on, e.examined_time, e.condition_note, e.purpose, e.letter_number,
                          p.medical_record_number, p.national_id, p.full_name, p.birth_place, p.birth_date, p.sex, p.address, p.insurance_type,
                          v.blood_group, v.rhesus,
                          b.hgb, b.wbc, b.plt, b.rbc, b.hct, b.gds, b.cholesterol, b.uric_acid, b.sgot, b.sgpt,
                          w.widal_o, w.widal_ao, w.widal_bo, w.widal_h',
        string $joins = ' LEFT JOIN vital_results v ON v.examination_id = e.id
                          LEFT JOIN blood_results b ON b.examination_id = e.id
                          LEFT JOIN widal_results w ON w.examination_id = e.id'
    ): array
    {
        $clauses = ['1 = 1', 'p.archived_at IS NULL'];
        $parameters = [];
        $dateFrom = self::date($filters['date_from'] ?? null);
        $dateTo = self::date($filters['date_to'] ?? null);
        $type = (string) ($filters['type'] ?? '');
        $insuranceType = trim((string) ($filters['insurance_type'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $query = trim((string) ($filters['q'] ?? ''));
        if ($dateFrom !== null) { $clauses[] = 'e.examined_on >= :date_from'; $parameters['date_from'] = $dateFrom; }
        if ($dateTo !== null) { $clauses[] = 'e.examined_on <= :date_to'; $parameters['date_to'] = $dateTo; }
        if (ExaminationType::isValid($type)) { $clauses[] = 'e.examination_type = :type'; $parameters['type'] = $type; }
        $panel = trim((string) ($filters['panel'] ?? ''));
        if ($panel !== '') {
            $panelConditions = ['EXISTS (
                SELECT 1 FROM examination_panel_selections ps
                INNER JOIN examination_panels ep ON ep.id = ps.panel_id
                WHERE ps.examination_id = e.id AND ep.code = :report_panel
            )', 'EXISTS (
                SELECT 1 FROM examination_parameter_results pr
                INNER JOIN test_parameters tp ON tp.id = pr.parameter_id
                INNER JOIN examination_panels ep ON ep.id = tp.panel_id
                WHERE pr.examination_id = e.id AND ep.code = :report_result_panel
            )'];
            $legacy = self::legacyPanelFilter($panel);
            if ($legacy !== null) $panelConditions[] = $legacy;
            $clauses[] = '(' . implode(' OR ', $panelConditions) . ')';
            $parameters['report_panel'] = $panel;
            $parameters['report_result_panel'] = $panel;
        }
        if (in_array($filters['sex'] ?? '', ['L', 'P'], true)) {
            $clauses[] = 'p.sex = :report_sex';
            $parameters['report_sex'] = $filters['sex'];
        }
        if (in_array($insuranceType, ['Umum', 'BPJS Kesehatan', 'Pemerintah Daerah Kota'], true)) {
            $clauses[] = 'p.insurance_type = :insurance_type';
            $parameters['insurance_type'] = $insuranceType;
        }
        if (in_array($status, ['draft', 'completed', 'finalized'], true)) { $clauses[] = 'e.status = :status'; $parameters['status'] = $status; }
        if ($query !== '') {
            $clauses[] = '(e.lab_number LIKE :query_lab OR p.medical_record_number LIKE :query_rm OR p.national_id LIKE :query_nik OR p.full_name LIKE :query_name)';
            $like = '%' . $query . '%';
            $parameters['query_lab'] = $like;
            $parameters['query_rm'] = $like;
            $parameters['query_nik'] = $like;
            $parameters['query_name'] = $like;
        }

        return ['SELECT ' . $select . ' FROM examinations e INNER JOIN patients p ON p.id = e.patient_id' . $joins . ' WHERE ' . implode(' AND ', $clauses), $parameters];
    }

    private static function legacyPanelFilter(string $panel): ?string
    {
        $definition = match ($panel) {
            'hematology_complete' => ['blood_results', ['hgb', 'wbc', 'plt', 'rbc', 'hct', 'lymph_pct', 'mid_pct', 'gran_pct', 'mcv', 'mch', 'mchc', 'eosinophil', 'basophil', 'neutrophil', 'lymphocyte', 'monocyte']],
            'clinical_chemistry' => ['blood_results', ['gds', 'fasting_glucose', 'glucose_2h_pp', 'urea', 'creatinine', 'cholesterol', 'uric_acid', 'sgot', 'sgpt']],
            'urinalysis_complete' => ['urine_results', ['color', 'clarity', 'specific_gravity', 'ph', 'protein', 'glucose_reduction', 'urobilinogen', 'bilirubin', 'occult_blood', 'ketone', 'nitrite', 'leukocyte_esterase', 'erythrocytes', 'leukocytes', 'squamous_cells', 'casts', 'crystals', 'bacteria']],
            'widal' => ['widal_results', ['widal_o', 'widal_ao', 'widal_bo', 'widal_h']],
            'immunoserology' => ['vital_results', ['blood_group', 'rhesus']],
            default => null,
        };
        if ($definition === null) return null;
        [$table, $columns] = $definition;
        $filled = array_map(static fn (string $column): string => 'NULLIF(TRIM(lp.' . $column . '), "") IS NOT NULL', $columns);
        return 'EXISTS (SELECT 1 FROM ' . $table . ' lp WHERE lp.examination_id = e.id AND (' . implode(' OR ', $filled) . '))';
    }

    private static function date(mixed $value): ?string
    {
        $value = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    private static function safeCsv(mixed $value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }

    private static function typeLabel(string $type): string
    {
        return ExaminationType::labels()[$type] ?? $type;
    }

    private static function statusLabel(string $status): string
    {
        return 'Tersimpan';
    }
}
