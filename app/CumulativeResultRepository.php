<?php

declare(strict_types=1);

final class CumulativeResultRepository
{
    /**
     * @return array{
     *     examinations:list<array{id:int,lab_number:string,examination_type:string,status:string,examined_on:string,examined_time:?string}>,
     *     parameters:list<array{code:string,label:string,unit:?string,result_type:string,panel_code:string,panel_name:string,panel_display_order:int,parameter_display_order:int}>,
     *     categories:list<array{code:string,name:string,display_order:int,parameters:list<array{code:string,label:string,unit:?string,result_type:string,panel_code:string,panel_name:string,panel_display_order:int,parameter_display_order:int}>}>,
     *     category_options:list<array{code:string,name:string,display_order:int}>,
     *     reference_summaries:array<string,array{available_count:int,entries:list<array{text:string,examined_on:list<string>}>}>,
     *     results:array<int,array<string,array{value:string,numeric:bool,unit:?string,source:string,interpretation:?string,reference_text:?string,reference_range_id:?int,assessment_source:?string}>>
     * }
     */
    public static function forPatient(int $patientId, ?PDO $pdo = null, array $filters = []): array
    {
        $pdo ??= Database::connection();
        $filters = self::normalizeFilters($filters);
        $sourceRows = self::examinations($pdo, $patientId, $filters);
        $categoryOptions = self::categoryOptions($pdo);
        if ($sourceRows === []) {
            return ['examinations' => [], 'parameters' => [], 'categories' => [], 'category_options' => $categoryOptions, 'reference_summaries' => [], 'results' => []];
        }

        $examinations = array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'lab_number' => (string) $row['lab_number'],
                'examination_type' => (string) $row['examination_type'],
                'status' => (string) $row['status'],
                'examined_on' => (string) $row['examined_on'],
                'examined_time' => $row['examined_time'] === null ? null : (string) $row['examined_time'],
            ],
            $sourceRows
        );
        $examinationIds = array_column($examinations, 'id');
        $catalog = self::parameterCatalog($pdo);

        /** @var array<string,array{code:string,label:string,unit:?string,result_type:string,panel_code:string,panel_name:string,panel_display_order:int,parameter_display_order:int}> $parameters */
        $parameters = [];
        /** @var array<int,array<string,array{value:string,numeric:bool,unit:?string,source:string,interpretation:?string,reference_text:?string,reference_range_id:?int,assessment_source:?string}>> $results */
        $results = [];
        /** @var array<int,array<string,true>> $modernCodes */
        $modernCodes = [];

        foreach (self::modernResults($pdo, $examinationIds) as $row) {
            $examinationId = (int) $row['examination_id'];
            $code = strtoupper(trim((string) $row['parameter_code_snapshot']));
            if ($code === '') {
                continue;
            }

            $numeric = $row['value_numeric'] !== null;
            $value = $numeric
                ? self::trimNumeric((string) $row['value_numeric'])
                : trim((string) $row['value_text']);
            if ($value === '') {
                continue;
            }

            $metadata = $catalog[$code] ?? [
                'code' => $code,
                'label' => (string) $row['parameter_label_snapshot'],
                'unit' => $row['unit_snapshot'] === null ? null : (string) $row['unit_snapshot'],
                'result_type' => $numeric ? 'numeric' : 'text',
                'panel_code' => (string) ($row['panel_code'] ?? 'other'),
                'panel_name' => (string) ($row['panel_name'] ?? 'Pemeriksaan Lainnya'),
                'panel_display_order' => (int) ($row['panel_display_order'] ?? 9999),
                'parameter_display_order' => (int) ($row['parameter_display_order'] ?? 9999),
            ];
            $metadata['label'] = (string) $row['parameter_label_snapshot'];
            $metadata['unit'] = $row['unit_snapshot'] === null ? $metadata['unit'] : (string) $row['unit_snapshot'];
            $parameters[$code] ??= $metadata;

            $unit = $row['unit_snapshot'] === null ? $metadata['unit'] : (string) $row['unit_snapshot'];
            $results[$examinationId][$code] = [
                'value' => $value,
                'numeric' => $numeric,
                'unit' => $unit,
                'source' => 'modern',
                'interpretation' => null,
                'reference_text' => null,
                'reference_range_id' => null,
                'assessment_source' => null,
            ];
            $modernCodes[$examinationId][$code] = true;
        }

        $legacyTests = LabTestCatalog::all();
        $legacyTests['BLOOD_GROUP'] = ['field' => 'blood_group', 'label' => 'Golongan Darah', 'section' => 'blood', 'kind' => 'qualitative'];
        $legacyTests['RHESUS'] = ['field' => 'rhesus', 'label' => 'Rhesus', 'section' => 'blood', 'kind' => 'qualitative'];
        $legacyDisplayOrders = [];
        foreach (array_keys($legacyTests) as $index => $legacyCode) {
            $legacyDisplayOrders[(string) $legacyCode] = $index + 1;
        }

        foreach ($sourceRows as $row) {
            $examinationId = (int) $row['id'];
            foreach ($legacyTests as $rawCode => $test) {
                $code = strtoupper((string) $rawCode);
                $value = $row[$test['field']] ?? null;
                if (isset($modernCodes[$examinationId][$code]) || $value === null || trim((string) $value) === '') {
                    continue;
                }

                $numeric = $test['kind'] === 'numeric';
                $metadata = $catalog[$code] ?? self::legacyMetadata($code, $test);
                if (!isset($catalog[$code])) {
                    $metadata['parameter_display_order'] = $legacyDisplayOrders[$rawCode];
                }
                $parameters[$code] ??= $metadata;
                $results[$examinationId][$code] = [
                    'value' => $numeric ? self::trimNumeric((string) $value) : trim((string) $value),
                    'numeric' => $numeric,
                    'unit' => $metadata['unit'],
                    'source' => 'legacy',
                    'interpretation' => null,
                    'reference_text' => null,
                    'reference_range_id' => null,
                    'assessment_source' => null,
                ];
            }
        }

        if ($filters['category'] !== '') {
            $parameters = array_filter(
                $parameters,
                static fn (array $parameter): bool => $parameter['panel_code'] === $filters['category']
            );
            $allowedCodes = array_fill_keys(array_keys($parameters), true);
            foreach ($results as $examinationId => $resultSet) {
                $results[$examinationId] = array_intersect_key($resultSet, $allowedCodes);
                if ($results[$examinationId] === []) {
                    unset($results[$examinationId]);
                }
            }
            $filteredResults = $results;
            $examinations = array_values(array_filter(
                $examinations,
                static fn (array $examination): bool => isset($filteredResults[$examination['id']])
            ));
        }

        if ($filters['limit'] !== null && count($examinations) > $filters['limit']) {
            $examinations = array_slice($examinations, -$filters['limit']);
            $keptExaminationIds = array_fill_keys(array_column($examinations, 'id'), true);
            $results = array_intersect_key($results, $keptExaminationIds);
        }

        $categories = self::categories(array_values($parameters));
        $sortedParameters = [];
        foreach ($categories as $category) {
            foreach ($category['parameters'] as $parameter) {
                $sortedParameters[] = $parameter;
            }
        }

        $results = HistoricalReferenceEvaluator::annotate($pdo, $patientId, $examinations, $results);
        $referenceSummaries = self::referenceSummaries($examinations, $sortedParameters, $results);

        return [
            'examinations' => $examinations,
            'parameters' => $sortedParameters,
            'categories' => $categories,
            'category_options' => $categoryOptions,
            'reference_summaries' => $referenceSummaries,
            'results' => $results,
        ];
    }

    /**
     * @param list<array{id:int,lab_number:string,examination_type:string,status:string,examined_on:string,examined_time:?string}> $examinations
     * @param list<array{code:string,label:string,unit:?string,result_type:string,panel_code:string,panel_name:string,panel_display_order:int,parameter_display_order:int}> $parameters
     * @param array<int,array<string,array{value:string,numeric:bool,unit:?string,source:string,interpretation:?string,reference_text:?string,reference_range_id:?int,assessment_source:?string}>> $results
     * @return array<string,array{available_count:int,entries:list<array{text:string,examined_on:list<string>}>}>
     */
    private static function referenceSummaries(array $examinations, array $parameters, array $results): array
    {
        $summaries = [];
        foreach ($parameters as $parameter) {
            $entries = [];
            $availableCount = 0;
            foreach ($examinations as $examination) {
                $result = $results[$examination['id']][$parameter['code']] ?? null;
                $referenceText = trim((string) ($result['reference_text'] ?? ''));
                if ($result === null || $referenceText === '' || ($result['interpretation'] ?? null) === 'not_assessed') {
                    continue;
                }

                $availableCount++;
                if (!isset($entries[$referenceText])) {
                    $entries[$referenceText] = ['text' => $referenceText, 'examined_on' => []];
                }
                $entries[$referenceText]['examined_on'][] = $examination['examined_on'];
            }
            $summaries[$parameter['code']] = [
                'available_count' => $availableCount,
                'entries' => array_values($entries),
            ];
        }

        return $summaries;
    }

    /** @param array<string,mixed> $filters @return array{from:string,to:string,category:string,limit:?int} */
    public static function normalizeFilters(array $filters): array
    {
        $from = self::validDate($filters['from'] ?? '');
        $to = self::validDate($filters['to'] ?? '');
        $category = trim((string) ($filters['category'] ?? ''));
        $limitValue = strtolower(trim((string) ($filters['limit'] ?? '5')));
        $limit = in_array($limitValue, ['all', 'semua'], true) ? null : (int) $limitValue;
        if (!in_array($limit, [5, 10], true) && $limit !== null) {
            $limit = 5;
        }

        return ['from' => $from, 'to' => $to, 'category' => $category, 'limit' => $limit];
    }

    /**
     * @param list<array{code:string,label:string,unit:?string,result_type:string,panel_code:string,panel_name:string,panel_display_order:int,parameter_display_order:int}> $parameters
     * @return list<array{code:string,name:string,display_order:int,parameters:list<array{code:string,label:string,unit:?string,result_type:string,panel_code:string,panel_name:string,panel_display_order:int,parameter_display_order:int}>}>
     */
    private static function categories(array $parameters): array
    {
        usort($parameters, static function (array $left, array $right): int {
            return $left['panel_display_order'] <=> $right['panel_display_order']
                ?: strcasecmp($left['panel_name'], $right['panel_name'])
                ?: $left['parameter_display_order'] <=> $right['parameter_display_order']
                ?: strcasecmp($left['label'], $right['label'])
                ?: strcmp($left['code'], $right['code']);
        });

        $categories = [];
        foreach ($parameters as $parameter) {
            $code = $parameter['panel_code'];
            if (!isset($categories[$code])) {
                $categories[$code] = [
                    'code' => $code,
                    'name' => $parameter['panel_name'],
                    'display_order' => $parameter['panel_display_order'],
                    'parameters' => [],
                ];
            }
            $categories[$code]['parameters'][] = $parameter;
        }

        return array_values($categories);
    }

    /** @return list<array<string,mixed>> */
    /** @param array{from:string,to:string,category:string,limit:?int} $filters @return list<array<string,mixed>> */
    private static function examinations(PDO $pdo, int $patientId, array $filters): array
    {
        $where = ['e.patient_id = :patient_id', 'e.examined_on >= :retention_start'];
        $parameters = [
            'patient_id' => $patientId,
            'retention_start' => ExaminationRepository::DATA_RETENTION_START,
        ];
        if ($filters['from'] !== '') {
            $where[] = 'e.examined_on >= :from_date';
            $parameters['from_date'] = $filters['from'];
        }
        if ($filters['to'] !== '') {
            $where[] = 'e.examined_on <= :to_date';
            $parameters['to_date'] = $filters['to'];
        }
        $statement = $pdo->prepare(
            'SELECT e.id, e.lab_number, e.examination_type, e.status, e.examined_on, e.examined_time,
                    b.hgb, b.wbc, b.plt, b.rbc, b.hct, b.lymph_pct, b.mid_pct, b.gran_pct, b.mcv, b.mch, b.mchc,
                    b.eosinophil, b.basophil, b.neutrophil, b.lymphocyte, b.monocyte, b.gds, b.fasting_glucose, b.glucose_2h_pp,
                    b.urea, b.creatinine, b.cholesterol, b.uric_acid, b.sgot, b.sgpt,
                    u.color AS urine_color, u.clarity AS urine_clarity, u.specific_gravity AS urine_specific_gravity, u.ph AS urine_ph,
                    u.protein, u.glucose_reduction, u.urobilinogen, u.bilirubin, u.occult_blood, u.ketone, u.nitrite,
                    u.leukocyte_esterase, u.erythrocytes, u.leukocytes, u.squamous_cells, u.casts, u.crystals, u.bacteria,
                    d.amphetamine AS drug_amphetamine, d.marijuana AS drug_marijuana, d.morphine AS drug_morphine,
                    d.benzodiazepine AS drug_benzodiazepine, d.methamphetamine AS drug_methamphetamine, d.cocaine AS drug_cocaine,
                    w.widal_o, w.widal_ao, w.widal_bo, w.widal_h,
                    v.blood_group, v.rhesus
             FROM examinations e
             LEFT JOIN blood_results b ON b.examination_id = e.id
             LEFT JOIN urine_results u ON u.examination_id = e.id
             LEFT JOIN drug_results d ON d.examination_id = e.id
             LEFT JOIN widal_results w ON w.examination_id = e.id
             LEFT JOIN vital_results v ON v.examination_id = e.id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY e.examined_on ASC, e.id ASC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @param list<int> $examinationIds @return list<array<string,mixed>> */
    private static function modernResults(PDO $pdo, array $examinationIds): array
    {
        $placeholders = [];
        $parameters = [];
        foreach ($examinationIds as $index => $examinationId) {
            $name = 'examination_id_' . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = $examinationId;
        }

        $statement = $pdo->prepare(
            'SELECT r.examination_id, r.parameter_code_snapshot, r.parameter_label_snapshot, r.unit_snapshot,
                    r.value_numeric, r.value_text, tp.result_type,
                    ep.code AS panel_code, ep.name AS panel_name, ep.display_order AS panel_display_order,
                    tp.display_order AS parameter_display_order
             FROM examination_parameter_results r
             LEFT JOIN test_parameters tp ON tp.id = r.parameter_id
             LEFT JOIN examination_panels ep ON ep.id = tp.panel_id
             WHERE r.examination_id IN (' . implode(', ', $placeholders) . ')
               AND (r.value_numeric IS NOT NULL OR NULLIF(TRIM(r.value_text), "") IS NOT NULL)
             ORDER BY r.examination_id, COALESCE(ep.display_order, 9999), COALESCE(tp.display_order, 9999), r.id'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * @return array<string,array{code:string,label:string,unit:?string,result_type:string,panel_code:string,panel_name:string,panel_display_order:int,parameter_display_order:int}>
     */
    private static function parameterCatalog(PDO $pdo): array
    {
        $statement = $pdo->query(
            'SELECT tp.code, tp.label, tp.unit, tp.result_type, tp.display_order AS parameter_display_order,
                    ep.code AS panel_code, ep.name AS panel_name, ep.display_order AS panel_display_order
             FROM test_parameters tp
             INNER JOIN examination_panels ep ON ep.id = tp.panel_id
             ORDER BY ep.display_order, tp.display_order, tp.id'
        );
        $catalog = [];
        foreach ($statement->fetchAll() as $row) {
            $code = strtoupper((string) $row['code']);
            $catalog[$code] = [
                'code' => $code,
                'label' => (string) $row['label'],
                'unit' => $row['unit'] === null ? null : (string) $row['unit'],
                'result_type' => (string) $row['result_type'],
                'panel_code' => (string) $row['panel_code'],
                'panel_name' => (string) $row['panel_name'],
                'panel_display_order' => (int) $row['panel_display_order'],
                'parameter_display_order' => (int) $row['parameter_display_order'],
            ];
        }

        return $catalog;
    }

    /** @return list<array{code:string,name:string,display_order:int}> */
    private static function categoryOptions(PDO $pdo): array
    {
        $statement = $pdo->query(
            'SELECT code, name, display_order
             FROM examination_panels
             WHERE is_active = 1
             ORDER BY display_order, id'
        );
        $options = [];
        foreach ($statement->fetchAll() as $row) {
            $options[(string) $row['code']] = [
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'display_order' => (int) $row['display_order'],
            ];
        }
        if (!isset($options['drug_screen'])) {
            $options['drug_screen'] = ['code' => 'drug_screen', 'name' => 'Skrining Narkoba', 'display_order' => 70];
        }

        uasort($options, static fn (array $left, array $right): int => $left['display_order'] <=> $right['display_order']);

        return array_values($options);
    }

    /**
     * @param array{field:string,label:string,section:string,kind:string} $test
     * @return array{code:string,label:string,unit:?string,result_type:string,panel_code:string,panel_name:string,panel_display_order:int,parameter_display_order:int}
     */
    private static function legacyMetadata(string $code, array $test): array
    {
        if (str_starts_with($code, 'URINE_')) {
            $panel = ['code' => 'urinalysis_complete', 'name' => 'Urine Lengkap / Urinalisis', 'order' => 20];
        } elseif (str_starts_with($code, 'WIDAL_')) {
            $panel = ['code' => 'widal', 'name' => 'Widal / Pemeriksaan Terkait Demam Tifoid', 'order' => 40];
        } elseif (str_starts_with($code, 'DRUG_')) {
            $panel = ['code' => 'drug_screen', 'name' => 'Skrining Narkoba', 'order' => 70];
        } elseif (in_array($code, ['BLOOD_GROUP', 'RHESUS'], true)) {
            $panel = ['code' => 'immunoserology', 'name' => 'Imunoserologi', 'order' => 60];
        } elseif (in_array($code, ['GDS', 'FBS', 'GLUCOSE_2H_PP', 'UREA', 'CREATININE', 'CHOL', 'UA', 'SGOT', 'SGPT'], true)) {
            $panel = ['code' => 'clinical_chemistry', 'name' => 'Kimia Klinik', 'order' => 30];
        } else {
            $panel = ['code' => 'hematology_complete', 'name' => 'DL / Darah Lengkap / Hematologi', 'order' => 10];
        }

        return [
            'code' => $code,
            'label' => $test['label'],
            'unit' => null,
            'result_type' => $test['kind'] === 'numeric' ? 'numeric' : 'text',
            'panel_code' => $panel['code'],
            'panel_name' => $panel['name'],
            'panel_display_order' => $panel['order'],
            'parameter_display_order' => 9999,
        ];
    }

    private static function trimNumeric(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value === '' ? '0' : $value;
    }

    private static function validDate(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
    }
}
