<?php

declare(strict_types=1);

final class AnalyticsRepository
{
    public const EMPTY_INSURANCE = '__empty__';
    private const DATA_RETENTION_START = ExaminationRepository::DATA_RETENTION_START;

    /**
     * @param array<string,mixed> $input
     * @return array{
     *     filters:array{date_from:string,date_to:string,type:string,panel:string,insurance_type:string,sex:string,age_group:string,status:string},
     *     options:array{
     *         examination_types:array<string,string>,
     *         panels:list<array{code:string,name:string}>,
     *         insurance_types:list<string>,
     *         sexes:array<string,string>,
     *         age_groups:array<string,string>,
     *         statuses:array<string,string>
     *     },
     *     summary:array<string,mixed>,
     *     operational:array<string,mixed>,
     *     clinical:array<string,mixed>,
     *     quality:array<string,int>,
     *     generated_at:string
     * }
     */
    public static function dashboard(array $input, ?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $options = self::filterOptions($pdo);
        $filters = self::normalizeFilters($input, $options);
        [$where, $parameters] = self::where($filters);

        $statement = $pdo->prepare(
            'SELECT COUNT(*) AS total_examinations,
                    COUNT(DISTINCT e.patient_id) AS unique_patients,
                    MIN(e.examined_on) AS first_examined_on,
                    MAX(e.examined_on) AS last_examined_on
             FROM examinations e
             INNER JOIN patients p ON p.id = e.patient_id
             WHERE ' . implode(' AND ', $where)
        );
        $statement->execute($parameters);
        $row = $statement->fetch() ?: [];

        $summary = [
            'total_examinations' => (int) ($row['total_examinations'] ?? 0),
            'unique_patients' => (int) ($row['unique_patients'] ?? 0),
            'first_examined_on' => self::nullableString($row['first_examined_on'] ?? null),
            'last_examined_on' => self::nullableString($row['last_examined_on'] ?? null),
        ];
        $clinical = self::clinicalAnalytics($pdo, $where, $parameters, $filters, $summary['total_examinations']);
        $summary = array_merge($summary, $clinical['summary']);

        return [
            'filters' => $filters,
            'options' => $options,
            'summary' => $summary,
            'operational' => self::operational($pdo, $where, $parameters, $filters, $options, $summary),
            'clinical' => $clinical['clinical'],
            'quality' => $clinical['quality'],
            'generated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<string> $where
     * @param array<string,mixed> $parameters
     * @param array{date_from:string,date_to:string,type:string,panel:string,insurance_type:string,sex:string,age_group:string,status:string} $filters
     * @param array{examination_types:array<string,string>,panels:list<array{code:string,name:string}>,insurance_types:list<string>,sexes:array<string,string>,age_groups:array<string,string>,statuses:array<string,string>} $options
     * @param array{total_examinations:int,unique_patients:int,first_examined_on:?string,last_examined_on:?string} $summary
     * @return array<string,mixed>
     */
    private static function operational(PDO $pdo, array $where, array $parameters, array $filters, array $options, array $summary): array
    {
        return [
            'trend' => self::trend($pdo, $where, $parameters, $filters),
            'examination_types' => self::examinationTypeUsage($pdo, $where, $parameters, $options['examination_types'], $summary['total_examinations']),
            'panels' => self::panelUsage($pdo, $where, $parameters, $options['panels'], $summary['total_examinations']),
            'insurance' => self::insuranceDistribution($pdo, $where, $parameters, $summary['total_examinations'], $summary['unique_patients']),
            'demographics' => self::demographics($pdo, $where, $parameters, $options),
        ];
    }

    /**
     * @param list<string> $where
     * @param array<string,mixed> $parameters
     * @param array{date_from:string,date_to:string,type:string,panel:string,insurance_type:string,sex:string,age_group:string,status:string} $filters
     * @return array{summary:array<string,mixed>,clinical:array<string,mixed>,quality:array<string,int>}
     */
    private static function clinicalAnalytics(PDO $pdo, array $where, array $parameters, array $filters, int $totalExaminations): array
    {
        $examinations = self::clinicalExaminations($pdo, $where, $parameters);
        [$results, $metadata] = self::normalizedResults($pdo, $examinations, $filters['panel']);
        $results = HistoricalReferenceEvaluator::annotatePopulation($pdo, $examinations, $results);

        $totals = [
            'total_results' => 0,
            'assessed_results' => 0,
            'normal_results' => 0,
            'low_results' => 0,
            'high_results' => 0,
            'qualitative_abnormal_results' => 0,
            'abnormal_results' => 0,
            'not_assessed_results' => 0,
            'not_applicable_results' => 0,
            'parameters_without_reference' => 0,
        ];
        $parameterRows = [];
        foreach ($results as $resultSet) {
            foreach ($resultSet as $code => $result) {
                $interpretation = (string) ($result['interpretation'] ?? 'not_assessed');
                $notApplicable = ($result['unassessed_reason'] ?? null) === 'not_applicable';
                $totals['total_results']++;
                if ($notApplicable) {
                    $totals['not_applicable_results']++;
                } elseif ($interpretation === 'not_assessed') {
                    $totals['not_assessed_results']++;
                    if (($result['unassessed_reason'] ?? null) === 'no_active_reference') {
                        $totals['parameters_without_reference']++;
                    }
                } else {
                    $totals['assessed_results']++;
                    if ($interpretation === 'normal') {
                        $totals['normal_results']++;
                    } elseif ($interpretation === 'low') {
                        $totals['low_results']++;
                        $totals['abnormal_results']++;
                    } elseif ($interpretation === 'high') {
                        $totals['high_results']++;
                        $totals['abnormal_results']++;
                    } elseif ($interpretation === 'abnormal') {
                        $totals['qualitative_abnormal_results']++;
                        $totals['abnormal_results']++;
                    }
                }

                if (!isset($parameterRows[$code])) {
                    $parameter = $metadata[$code] ?? ['label' => $code, 'panel_name' => 'Pemeriksaan lainnya'];
                    $parameterRows[$code] = [
                        'code' => $code,
                        'label' => (string) $parameter['label'],
                        'panel_name' => (string) $parameter['panel_name'],
                        'total_results' => 0,
                        'assessed_results' => 0,
                        'normal_results' => 0,
                        'low_results' => 0,
                        'high_results' => 0,
                        'qualitative_abnormal_results' => 0,
                        'not_assessed_results' => 0,
                        'not_applicable_results' => 0,
                        'abnormal_results' => 0,
                        'abnormal_percentage' => 0.0,
                    ];
                }
                $parameterRows[$code]['total_results']++;
                if ($notApplicable) {
                    $parameterRows[$code]['not_applicable_results']++;
                } elseif ($interpretation === 'not_assessed') {
                    $parameterRows[$code]['not_assessed_results']++;
                } else {
                    $parameterRows[$code]['assessed_results']++;
                    $key = match ($interpretation) {
                        'normal' => 'normal_results',
                        'low' => 'low_results',
                        'high' => 'high_results',
                        'abnormal' => 'qualitative_abnormal_results',
                        default => null,
                    };
                    if ($key !== null) {
                        $parameterRows[$code][$key]++;
                    }
                    if (in_array($interpretation, ['low', 'high', 'abnormal'], true)) {
                        $parameterRows[$code]['abnormal_results']++;
                    }
                }
            }
        }

        foreach ($parameterRows as &$row) {
            $row['abnormal_percentage'] = self::percentage($row['abnormal_results'], $row['assessed_results']);
        }
        unset($row);
        usort($parameterRows, static fn (array $left, array $right): int =>
            $right['abnormal_results'] <=> $left['abnormal_results']
            ?: $right['not_assessed_results'] <=> $left['not_assessed_results']
            ?: strcasecmp($left['label'], $right['label'])
        );

        $examinationsWithResults = count($results);
        $withoutResults = 0;
        $draft = 0;
        $missingBirthDate = 0;
        $missingSex = 0;
        $missingInsurance = 0;
        foreach ($examinations as $examination) {
            $id = (int) $examination['id'];
            if ($examination['status'] === 'draft') {
                $draft++;
            }
            if (in_array($examination['status'], ['completed', 'finalized'], true) && !isset($results[$id])) {
                $withoutResults++;
            }
            if (self::nullableString($examination['birth_date'] ?? null) === null
                || (string) $examination['birth_date'] > (string) $examination['examined_on']) {
                $missingBirthDate++;
            }
            if (!in_array($examination['sex'] ?? null, ['L', 'P'], true)) {
                $missingSex++;
            }
            if (self::nullableString($examination['insurance_type'] ?? null) === null) {
                $missingInsurance++;
            }
        }

        return [
            'summary' => [
                'abnormal_results' => $totals['abnormal_results'],
                'abnormal_percentage' => self::percentage($totals['abnormal_results'], $totals['assessed_results']),
                'examinations_with_results' => $examinationsWithResults,
                'examinations_with_results_percentage' => self::percentage($examinationsWithResults, $totalExaminations),
            ],
            'clinical' => [
                'totals' => $totals,
                'parameters' => array_values($parameterRows),
            ],
            'quality' => [
                'draft_examinations' => $draft,
                'completed_without_results' => $withoutResults,
                'incomplete_modern_panels' => self::incompleteModernPanels($pdo, $where, $parameters, $filters['panel']),
                'missing_birth_date' => $missingBirthDate,
                'missing_sex' => $missingSex,
                'missing_insurance' => $missingInsurance,
                'parameters_without_reference' => $totals['parameters_without_reference'],
            ],
        ];
    }

    /** @param list<string> $where @param array<string,mixed> $parameters @return list<array<string,mixed>> */
    private static function clinicalExaminations(PDO $pdo, array $where, array $parameters): array
    {
        $statement = $pdo->prepare(
            'SELECT e.id, e.patient_id, e.status, e.examined_on,
                    p.sex, p.birth_date, p.insurance_type,
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
             INNER JOIN patients p ON p.id = e.patient_id
             LEFT JOIN blood_results b ON b.examination_id = e.id
             LEFT JOIN urine_results u ON u.examination_id = e.id
             LEFT JOIN drug_results d ON d.examination_id = e.id
             LEFT JOIN widal_results w ON w.examination_id = e.id
             LEFT JOIN vital_results v ON v.examination_id = e.id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY e.examined_on, e.id'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * @param list<array<string,mixed>> $examinations
     * @return array{0:array<int,array<string,array<string,mixed>>>,1:array<string,array<string,mixed>>}
     */
    private static function normalizedResults(PDO $pdo, array $examinations, string $panelFilter): array
    {
        if ($examinations === []) {
            return [[], []];
        }

        $examinationIds = array_map(static fn (array $row): int => (int) $row['id'], $examinations);
        [$placeholders, $bindings] = self::bindings($examinationIds, 'clinical_examination');
        $statement = $pdo->prepare(
            'SELECT r.examination_id, r.parameter_code_snapshot, r.parameter_label_snapshot, r.unit_snapshot,
                    r.value_numeric, r.value_text, tp.result_type,
                    ep.code AS panel_code, ep.name AS panel_name
             FROM examination_parameter_results r
             LEFT JOIN test_parameters tp ON tp.id = r.parameter_id
             LEFT JOIN examination_panels ep ON ep.id = tp.panel_id
             WHERE r.examination_id IN (' . implode(', ', $placeholders) . ')
               AND (r.value_numeric IS NOT NULL OR NULLIF(TRIM(r.value_text), "") IS NOT NULL)
             ORDER BY r.examination_id, r.id'
        );
        $statement->execute($bindings);

        $results = [];
        $metadata = [];
        $modernCodes = [];
        foreach ($statement->fetchAll() as $row) {
            $examinationId = (int) $row['examination_id'];
            $code = strtoupper(trim((string) $row['parameter_code_snapshot']));
            $panelCode = trim((string) ($row['panel_code'] ?? 'other')) ?: 'other';
            if ($code === '' || ($panelFilter !== '' && $panelCode !== $panelFilter)) {
                continue;
            }
            $numeric = $row['value_numeric'] !== null;
            $value = $numeric ? self::trimNumeric((string) $row['value_numeric']) : trim((string) $row['value_text']);
            if ($value === '') {
                continue;
            }

            $metadata[$code] ??= [
                'label' => trim((string) $row['parameter_label_snapshot']) ?: $code,
                'panel_code' => $panelCode,
                'panel_name' => trim((string) ($row['panel_name'] ?? '')) ?: 'Pemeriksaan lainnya',
            ];
            $results[$examinationId][$code] = [
                'value' => $value,
                'numeric' => $numeric,
                'source' => 'modern',
                'interpretation' => null,
                'reference_text' => null,
                'reference_range_id' => null,
                'assessment_source' => null,
                'unassessed_reason' => null,
            ];
            $modernCodes[$examinationId][$code] = true;
        }

        $legacyTests = LabTestCatalog::all();
        $legacyTests['BLOOD_GROUP'] = ['field' => 'blood_group', 'label' => 'Golongan Darah', 'section' => 'blood', 'kind' => 'qualitative'];
        $legacyTests['RHESUS'] = ['field' => 'rhesus', 'label' => 'Rhesus', 'section' => 'blood', 'kind' => 'qualitative'];
        foreach ($examinations as $examination) {
            $examinationId = (int) $examination['id'];
            foreach ($legacyTests as $rawCode => $test) {
                $code = strtoupper((string) $rawCode);
                $panel = self::legacyParameterPanel($code);
                $value = $examination[$test['field']] ?? null;
                if (isset($modernCodes[$examinationId][$code])
                    || ($panelFilter !== '' && $panel['code'] !== $panelFilter)
                    || $value === null
                    || trim((string) $value) === '') {
                    continue;
                }

                $numeric = $test['kind'] === 'numeric';
                $metadata[$code] ??= [
                    'label' => (string) $test['label'],
                    'panel_code' => $panel['code'],
                    'panel_name' => $panel['name'],
                ];
                $results[$examinationId][$code] = [
                    'value' => $numeric ? self::trimNumeric((string) $value) : trim((string) $value),
                    'numeric' => $numeric,
                    'source' => 'legacy',
                    'interpretation' => null,
                    'reference_text' => null,
                    'reference_range_id' => null,
                    'assessment_source' => null,
                    'unassessed_reason' => null,
                ];
            }
        }

        return [$results, $metadata];
    }

    /** @return array{code:string,name:string} */
    private static function legacyParameterPanel(string $code): array
    {
        return match (true) {
            str_starts_with($code, 'URINE_') => ['code' => 'urinalysis_complete', 'name' => 'Urine Lengkap / Urinalisis'],
            str_starts_with($code, 'WIDAL_') => ['code' => 'widal', 'name' => 'Widal / Pemeriksaan Terkait Demam Tifoid'],
            str_starts_with($code, 'DRUG_') => ['code' => 'drug_screen', 'name' => 'Skrining Narkoba'],
            in_array($code, ['BLOOD_GROUP', 'RHESUS'], true) => ['code' => 'immunoserology', 'name' => 'Imunoserologi'],
            in_array($code, ['GDS', 'FBS', 'GLUCOSE_2H_PP', 'UREA', 'CREATININE', 'CHOL', 'UA', 'SGOT', 'SGPT'], true) => ['code' => 'clinical_chemistry', 'name' => 'Kimia Klinik'],
            default => ['code' => 'hematology_complete', 'name' => 'DL / Darah Lengkap / Hematologi'],
        };
    }

    /** @param list<string> $where @param array<string,mixed> $parameters */
    private static function incompleteModernPanels(PDO $pdo, array $where, array $parameters, string $panelFilter): int
    {
        $panelClause = '';
        if ($panelFilter !== '') {
            $panelClause = ' AND ep.code = :quality_panel';
            $parameters['quality_panel'] = $panelFilter;
        }
        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM (
                 SELECT s.examination_id, s.panel_id
                 FROM examination_panel_selections s
                 INNER JOIN examinations e ON e.id = s.examination_id
                 INNER JOIN patients p ON p.id = e.patient_id
                 INNER JOIN examination_panels ep ON ep.id = s.panel_id
                 INNER JOIN test_parameters tp ON tp.panel_id = s.panel_id AND tp.is_active = 1
                 LEFT JOIN examination_parameter_results r
                    ON r.examination_id = s.examination_id AND r.parameter_id = tp.id
                 WHERE ' . implode(' AND ', $where) . $panelClause . '
                 GROUP BY s.examination_id, s.panel_id
                 HAVING COUNT(DISTINCT tp.id) > COUNT(DISTINCT CASE
                     WHEN r.value_numeric IS NOT NULL OR NULLIF(TRIM(r.value_text), "") IS NOT NULL THEN tp.id
                     ELSE NULL
                 END)
                 AND (MAX(s.is_auto_included) = 0 OR COUNT(DISTINCT CASE
                     WHEN r.value_numeric IS NOT NULL OR NULLIF(TRIM(r.value_text), "") IS NOT NULL THEN tp.id
                     ELSE NULL
                 END) > 0)
             ) incomplete_panels'
        );
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /** @param list<int|string> $values @return array{0:list<string>,1:array<string,int|string>} */
    private static function bindings(array $values, string $prefix): array
    {
        $placeholders = [];
        $bindings = [];
        foreach (array_values($values) as $index => $value) {
            $name = $prefix . '_' . $index;
            $placeholders[] = ':' . $name;
            $bindings[$name] = $value;
        }

        return [$placeholders, $bindings];
    }

    private static function trimNumeric(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value === '' ? '0' : $value;
    }

    /** @param list<string> $where @param array<string,mixed> $parameters @param array{date_from:string,date_to:string,type:string,panel:string,insurance_type:string,sex:string,age_group:string,status:string} $filters */
    private static function trend(PDO $pdo, array $where, array $parameters, array $filters): array
    {
        $days = (int) (new DateTimeImmutable($filters['date_from']))->diff(new DateTimeImmutable($filters['date_to']))->format('%a') + 1;
        if ($days <= 31) {
            $grouping = 'day';
            $groupBy = 'e.examined_on';
        } elseif ($days <= 180) {
            $grouping = 'week';
            $groupBy = 'YEARWEEK(e.examined_on, 3)';
        } else {
            $grouping = 'month';
            $groupBy = 'YEAR(e.examined_on), MONTH(e.examined_on)';
        }

        $statement = $pdo->prepare(
            'SELECT MIN(e.examined_on) AS period_start,
                    MAX(e.examined_on) AS period_end,
                    COUNT(*) AS total_examinations,
                    COUNT(DISTINCT e.patient_id) AS unique_patients
             FROM examinations e
             INNER JOIN patients p ON p.id = e.patient_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY ' . $groupBy . '
             ORDER BY period_start'
        );
        $statement->execute($parameters);

        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $start = (string) $row['period_start'];
            $end = (string) $row['period_end'];
            $rows[] = [
                'label' => self::periodLabel($start, $end, $grouping),
                'period_start' => $start,
                'period_end' => $end,
                'total_examinations' => (int) $row['total_examinations'],
                'unique_patients' => (int) $row['unique_patients'],
            ];
        }

        return ['grouping' => $grouping, 'rows' => $rows];
    }

    /** @param list<string> $where @param array<string,mixed> $parameters @param array<string,string> $labels */
    private static function examinationTypeUsage(PDO $pdo, array $where, array $parameters, array $labels, int $total): array
    {
        $statement = $pdo->prepare(
            'SELECT e.examination_type AS code, COUNT(*) AS total
             FROM examinations e
             INNER JOIN patients p ON p.id = e.patient_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY e.examination_type
             ORDER BY total DESC, code
             LIMIT 10'
        );
        $statement->execute($parameters);

        return self::usageRows($statement->fetchAll(), $labels, $total);
    }

    /** @param list<string> $where @param array<string,mixed> $parameters @param list<array{code:string,name:string}> $panels */
    private static function panelUsage(PDO $pdo, array $where, array $parameters, array $panels, int $total): array
    {
        $labels = [];
        foreach ($panels as $panel) {
            $labels[$panel['code']] = $panel['name'];
        }

        $statement = $pdo->prepare(
            'SELECT source.code, COUNT(DISTINCT source.examination_id) AS total
             FROM (' . self::panelResultUnion() . ') source
             INNER JOIN examinations e ON e.id = source.examination_id
             INNER JOIN patients p ON p.id = e.patient_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY source.code
             ORDER BY total DESC, source.code
             LIMIT 10'
        );
        $statement->execute($parameters);

        return self::usageRows($statement->fetchAll(), $labels, $total);
    }

    /** @param list<string> $where @param array<string,mixed> $parameters */
    private static function insuranceDistribution(PDO $pdo, array $where, array $parameters, int $totalExaminations, int $totalUniquePatients): array
    {
        $statement = $pdo->prepare(
            'SELECT COALESCE(NULLIF(TRIM(p.insurance_type), ""), :empty_insurance) AS code,
                    COUNT(*) AS total_examinations,
                    COUNT(DISTINCT e.patient_id) AS unique_patients
             FROM examinations e
             INNER JOIN patients p ON p.id = e.patient_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY code
             ORDER BY total_examinations DESC, code'
        );
        $statement->execute(array_merge(['empty_insurance' => self::EMPTY_INSURANCE], $parameters));

        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $code = (string) $row['code'];
            $examinationCount = (int) $row['total_examinations'];
            $uniquePatientCount = (int) $row['unique_patients'];
            $rows[] = [
                'code' => $code,
                'label' => $code === self::EMPTY_INSURANCE ? 'Belum diisi' : $code,
                'total' => $examinationCount,
                'percentage' => self::percentage($examinationCount, $totalExaminations),
                'unique_patients' => $uniquePatientCount,
                'unique_percentage' => self::percentage($uniquePatientCount, $totalUniquePatients),
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $where
     * @param array<string,mixed> $parameters
     * @param array{sexes:array<string,string>,age_groups:array<string,string>} $options
     * @return array{sexes:list<array<string,mixed>>,age_groups:list<array<string,mixed>>}
     */
    private static function demographics(PDO $pdo, array $where, array $parameters, array $options): array
    {
        $statement = $pdo->prepare(
            'SELECT e.patient_id, MAX(e.examined_on) AS latest_examined_on, p.sex, p.birth_date
             FROM examinations e
             INNER JOIN patients p ON p.id = e.patient_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY e.patient_id, p.sex, p.birth_date'
        );
        $statement->execute($parameters);

        $sexCounts = array_fill_keys(array_keys($options['sexes']), 0);
        $ageCounts = array_fill_keys(array_keys($options['age_groups']), 0);
        foreach ($statement->fetchAll() as $row) {
            $sex = in_array($row['sex'], ['L', 'P'], true) ? (string) $row['sex'] : 'unknown';
            $sexCounts[$sex]++;
            $ageCounts[self::ageGroup(self::nullableString($row['birth_date']), (string) $row['latest_examined_on'])]++;
        }

        return [
            'sexes' => self::distributionRows($sexCounts, $options['sexes']),
            'age_groups' => self::distributionRows($ageCounts, $options['age_groups']),
        ];
    }

    /** @param list<array<string,mixed>> $rows @param array<string,string> $labels @return list<array{code:string,label:string,total:int,percentage:float}> */
    private static function usageRows(array $rows, array $labels, int $total): array
    {
        $usage = [];
        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $count = (int) $row['total'];
            $usage[] = [
                'code' => $code,
                'label' => $labels[$code] ?? $code,
                'total' => $count,
                'percentage' => self::percentage($count, $total),
            ];
        }

        return $usage;
    }

    /** @param array<string,int> $counts @param array<string,string> $labels @return list<array{code:string,label:string,total:int,percentage:float}> */
    private static function distributionRows(array $counts, array $labels): array
    {
        $total = array_sum($counts);
        $rows = [];
        foreach ($labels as $code => $label) {
            $count = $counts[$code] ?? 0;
            $rows[] = [
                'code' => $code,
                'label' => $label,
                'total' => $count,
                'percentage' => self::percentage($count, $total),
            ];
        }

        return $rows;
    }

    private static function percentage(int $count, int $total): float
    {
        return $total === 0 ? 0.0 : round(($count / $total) * 100, 1);
    }

    private static function ageGroup(?string $birthDate, string $examinedOn): string
    {
        if ($birthDate === null || $birthDate > $examinedOn) {
            return 'unknown';
        }

        $age = (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable($examinedOn))->y;
        return match (true) {
            $age <= 17 => '0_17',
            $age <= 39 => '18_39',
            $age <= 59 => '40_59',
            default => '60_plus',
        };
    }

    private static function periodLabel(string $start, string $end, string $grouping): string
    {
        $startDate = new DateTimeImmutable($start);
        if ($grouping === 'month') {
            return $startDate->format('m/Y');
        }
        if ($grouping === 'week' && $start !== $end) {
            return $startDate->format('d/m') . '–' . (new DateTimeImmutable($end))->format('d/m/Y');
        }

        return $startDate->format('d/m/Y');
    }

    private static function panelResultUnion(): string
    {
        return 'SELECT apr.examination_id, ep.code
                FROM examination_parameter_results apr
                INNER JOIN test_parameters tp ON tp.id = apr.parameter_id
                INNER JOIN examination_panels ep ON ep.id = tp.panel_id
                WHERE apr.value_numeric IS NOT NULL OR NULLIF(TRIM(apr.value_text), "") IS NOT NULL
                UNION ALL
                SELECT br.examination_id, "hematology_complete"
                FROM blood_results br
                WHERE ' . self::filledColumns('br', ['hgb', 'wbc', 'plt', 'rbc', 'hct', 'lymph_pct', 'mid_pct', 'gran_pct', 'mcv', 'mch', 'mchc', 'eosinophil', 'basophil', 'neutrophil', 'lymphocyte', 'monocyte']) . '
                UNION ALL
                SELECT br.examination_id, "clinical_chemistry"
                FROM blood_results br
                WHERE ' . self::filledColumns('br', ['gds', 'fasting_glucose', 'glucose_2h_pp', 'urea', 'creatinine', 'cholesterol', 'uric_acid', 'sgot', 'sgpt']) . '
                UNION ALL
                SELECT ur.examination_id, "urinalysis_complete"
                FROM urine_results ur
                WHERE ' . self::filledColumns('ur', ['color', 'clarity', 'specific_gravity', 'ph', 'protein', 'glucose_reduction', 'urobilinogen', 'bilirubin', 'occult_blood', 'ketone', 'nitrite', 'leukocyte_esterase', 'erythrocytes', 'leukocytes', 'squamous_cells', 'casts', 'crystals', 'bacteria'], true) . '
                UNION ALL
                SELECT wr.examination_id, "widal"
                FROM widal_results wr
                WHERE ' . self::filledColumns('wr', ['widal_o', 'widal_ao', 'widal_bo', 'widal_h'], true) . '
                UNION ALL
                SELECT dr.examination_id, "drug_screen"
                FROM drug_results dr
                WHERE ' . self::filledColumns('dr', ['amphetamine', 'marijuana', 'morphine', 'benzodiazepine', 'methamphetamine', 'cocaine'], true) . '
                UNION ALL
                SELECT vr.examination_id, "immunoserology"
                FROM vital_results vr
                WHERE ' . self::filledColumns('vr', ['blood_group', 'rhesus'], true);
    }

    /** @param list<string> $columns */
    private static function filledColumns(string $alias, array $columns, bool $trimText = false): string
    {
        return '(' . implode(' OR ', array_map(
            static fn (string $column): string => $trimText
                ? 'NULLIF(TRIM(' . $alias . '.' . $column . '), "") IS NOT NULL'
                : $alias . '.' . $column . ' IS NOT NULL',
            $columns
        )) . ')';
    }

    /**
     * @return array{
     *     examination_types:array<string,string>,
     *     panels:list<array{code:string,name:string}>,
     *     insurance_types:list<string>,
     *     sexes:array<string,string>,
     *     age_groups:array<string,string>,
     *     statuses:array<string,string>
     * }
     */
    public static function filterOptions(?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $panels = [];
        foreach ($pdo->query(
            'SELECT code, name
             FROM examination_panels
             WHERE is_active = 1
             ORDER BY display_order, id'
        )->fetchAll() as $row) {
            $panels[(string) $row['code']] = [
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
            ];
        }
        if (!isset($panels['drug_screen'])) {
            $panels['drug_screen'] = ['code' => 'drug_screen', 'name' => 'Skrining Narkoba'];
        }

        return [
            'examination_types' => ExaminationType::labels(),
            'panels' => array_values($panels),
            'insurance_types' => array_keys(PatientRepository::INSURANCE_TYPES),
            'sexes' => [
                'L' => 'Laki-laki',
                'P' => 'Perempuan',
                'unknown' => 'Tidak diketahui',
            ],
            'age_groups' => [
                '0_17' => 'Anak (0–17 tahun)',
                '18_39' => 'Dewasa muda (18–39 tahun)',
                '40_59' => 'Dewasa (40–59 tahun)',
                '60_plus' => 'Lansia (60 tahun ke atas)',
                'unknown' => 'Tidak diketahui',
            ],
            'statuses' => [
                'results' => 'Semua data tersimpan',
                'draft' => 'Data legacy terverifikasi',
                'completed' => 'Tersimpan',
                'finalized' => 'Final',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array{
     *     examination_types:array<string,string>,
     *     panels:list<array{code:string,name:string}>,
     *     insurance_types:list<string>,
     *     sexes:array<string,string>,
     *     age_groups:array<string,string>,
     *     statuses:array<string,string>
     * } $options
     * @return array{date_from:string,date_to:string,type:string,panel:string,insurance_type:string,sex:string,age_group:string,status:string}
     */
    public static function normalizeFilters(array $input, array $options): array
    {
        $today = new DateTimeImmutable('today');
        $defaultFrom = $today->modify('first day of this month')->format('Y-m-d');
        $defaultTo = $today->format('Y-m-d');
        $errors = [];

        foreach (['date_from', 'date_to', 'type', 'panel', 'insurance_type', 'sex', 'age_group', 'status'] as $key) {
            if (array_key_exists($key, $input) && !is_scalar($input[$key])) {
                $errors[$key] = 'Format filter tidak valid.';
            }
        }

        $rawFrom = self::inputString($input['date_from'] ?? '');
        $rawTo = self::inputString($input['date_to'] ?? '');
        $dateFrom = $rawFrom === '' ? $defaultFrom : self::date($rawFrom);
        $dateTo = $rawTo === '' ? $defaultTo : self::date($rawTo);
        if ($dateFrom === null) {
            $errors['date_from'] = 'Tanggal awal tidak valid.';
        }
        if ($dateTo === null) {
            $errors['date_to'] = 'Tanggal akhir tidak valid.';
        }
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            $errors['date_range'] = 'Tanggal awal tidak boleh melewati tanggal akhir.';
        }
        if ($dateTo !== null && $dateTo < self::DATA_RETENTION_START) {
            $errors['date_to'] = 'Analisis hanya tersedia untuk pemeriksaan mulai 01/01/2026.';
        }
        if ($dateFrom !== null && $dateFrom < self::DATA_RETENTION_START) {
            $dateFrom = self::DATA_RETENTION_START;
        }

        $type = self::inputString($input['type'] ?? '');
        if ($type !== '' && !array_key_exists($type, $options['examination_types'])) {
            $errors['type'] = 'Jenis pemeriksaan tidak valid.';
        }

        $panel = self::inputString($input['panel'] ?? '');
        $panelCodes = array_column($options['panels'], 'code');
        if ($panel !== '' && !in_array($panel, $panelCodes, true)) {
            $errors['panel'] = 'Kategori pemeriksaan tidak valid.';
        }

        $insuranceType = self::inputString($input['insurance_type'] ?? '');
        if ($insuranceType !== ''
            && $insuranceType !== self::EMPTY_INSURANCE
            && !in_array($insuranceType, $options['insurance_types'], true)) {
            $errors['insurance_type'] = 'Pilihan asuransi atau penjamin tidak valid.';
        }

        $sex = self::inputString($input['sex'] ?? '');
        if ($sex !== '' && !array_key_exists($sex, $options['sexes'])) {
            $errors['sex'] = 'Pilihan jenis kelamin tidak valid.';
        }

        $ageGroup = self::inputString($input['age_group'] ?? '');
        if ($ageGroup !== '' && !array_key_exists($ageGroup, $options['age_groups'])) {
            $errors['age_group'] = 'Kelompok usia tidak valid.';
        }

        $status = self::inputString($input['status'] ?? 'results');
        $status = $status === '' ? 'results' : $status;
        if (!array_key_exists($status, $options['statuses'])) {
            $errors['status'] = 'Status pemeriksaan tidak valid.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'date_from' => (string) $dateFrom,
            'date_to' => (string) $dateTo,
            'type' => $type,
            'panel' => $panel,
            'insurance_type' => $insuranceType,
            'sex' => $sex,
            'age_group' => $ageGroup,
            'status' => $status,
        ];
    }

    /**
     * @param array{date_from:string,date_to:string,type:string,panel:string,insurance_type:string,sex:string,age_group:string,status:string} $filters
     * @return array{0:list<string>,1:array<string,mixed>}
     */
    private static function where(array $filters): array
    {
        $where = [
            'p.archived_at IS NULL',
            'e.examined_on >= :retention_start',
            'e.examined_on >= :date_from',
            'e.examined_on <= :date_to',
        ];
        $parameters = [
            'retention_start' => self::DATA_RETENTION_START,
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ];

        if ($filters['status'] !== 'results') {
            $where[] = 'e.status = :status';
            $parameters['status'] = $filters['status'];
        }

        if ($filters['type'] !== '') {
            $where[] = 'e.examination_type = :examination_type';
            $parameters['examination_type'] = $filters['type'];
        }
        if ($filters['insurance_type'] === self::EMPTY_INSURANCE) {
            $where[] = 'NULLIF(TRIM(p.insurance_type), "") IS NULL';
        } elseif ($filters['insurance_type'] !== '') {
            $where[] = 'TRIM(p.insurance_type) = :insurance_type';
            $parameters['insurance_type'] = $filters['insurance_type'];
        }
        if ($filters['sex'] === 'unknown') {
            $where[] = '(p.sex IS NULL OR p.sex NOT IN ("L", "P"))';
        } elseif ($filters['sex'] !== '') {
            $where[] = 'p.sex = :sex';
            $parameters['sex'] = $filters['sex'];
        }

        self::appendAgeClause($where, $filters['age_group']);

        if ($filters['panel'] !== '') {
            $parameters['panel_result_code'] = $filters['panel'];
            $panelConditions = [
                'EXISTS (
                    SELECT 1
                    FROM examination_parameter_results apr
                    INNER JOIN test_parameters atp ON atp.id = apr.parameter_id
                    INNER JOIN examination_panels aep ON aep.id = atp.panel_id
                    WHERE apr.examination_id = e.id
                      AND aep.code = :panel_result_code
                      AND (apr.value_numeric IS NOT NULL OR NULLIF(TRIM(apr.value_text), "") IS NOT NULL)
                )',
            ];
            $legacyCondition = self::legacyPanelCondition($filters['panel']);
            if ($legacyCondition !== null) {
                $panelConditions[] = $legacyCondition;
            }
            $where[] = '(' . implode(' OR ', $panelConditions) . ')';
        }

        return [$where, $parameters];
    }

    /** @param list<string> $where */
    private static function appendAgeClause(array &$where, string $ageGroup): void
    {
        if ($ageGroup === '') {
            return;
        }
        if ($ageGroup === 'unknown') {
            $where[] = '(p.birth_date IS NULL OR p.birth_date > e.examined_on)';
            return;
        }

        $ageExpression = 'TIMESTAMPDIFF(YEAR, p.birth_date, e.examined_on)';
        $knownBirthDate = 'p.birth_date IS NOT NULL AND p.birth_date <= e.examined_on';
        $where[] = match ($ageGroup) {
            '0_17' => '(' . $knownBirthDate . ' AND ' . $ageExpression . ' BETWEEN 0 AND 17)',
            '18_39' => '(' . $knownBirthDate . ' AND ' . $ageExpression . ' BETWEEN 18 AND 39)',
            '40_59' => '(' . $knownBirthDate . ' AND ' . $ageExpression . ' BETWEEN 40 AND 59)',
            '60_plus' => '(' . $knownBirthDate . ' AND ' . $ageExpression . ' >= 60)',
            default => '1 = 0',
        };
    }

    private static function legacyPanelCondition(string $panel): ?string
    {
        return match ($panel) {
            'hematology_complete' => self::legacyExists('blood_results', 'abr', [
                'hgb', 'wbc', 'plt', 'rbc', 'hct', 'lymph_pct', 'mid_pct', 'gran_pct',
                'mcv', 'mch', 'mchc', 'eosinophil', 'basophil', 'neutrophil', 'lymphocyte', 'monocyte',
            ]),
            'clinical_chemistry' => self::legacyExists('blood_results', 'abr', [
                'gds', 'fasting_glucose', 'glucose_2h_pp', 'urea', 'creatinine',
                'cholesterol', 'uric_acid', 'sgot', 'sgpt',
            ]),
            'urinalysis_complete' => self::legacyExists('urine_results', 'aur', [
                'color', 'clarity', 'specific_gravity', 'ph', 'protein', 'glucose_reduction',
                'urobilinogen', 'bilirubin', 'occult_blood', 'ketone', 'nitrite',
                'leukocyte_esterase', 'erythrocytes', 'leukocytes', 'squamous_cells',
                'casts', 'crystals', 'bacteria',
            ], true),
            'widal' => self::legacyExists('widal_results', 'awr', ['widal_o', 'widal_ao', 'widal_bo', 'widal_h'], true),
            'drug_screen' => self::legacyExists('drug_results', 'adr', [
                'amphetamine', 'marijuana', 'morphine', 'benzodiazepine', 'methamphetamine', 'cocaine',
            ], true),
            'immunoserology' => self::legacyExists('vital_results', 'avr', ['blood_group', 'rhesus']),
            default => null,
        };
    }

    /** @param list<string> $columns */
    private static function legacyExists(string $table, string $alias, array $columns, bool $trimText = false): string
    {
        $filled = array_map(
            static fn (string $column): string => $trimText
                ? 'NULLIF(TRIM(' . $alias . '.' . $column . '), "") IS NOT NULL'
                : $alias . '.' . $column . ' IS NOT NULL',
            $columns
        );

        return 'EXISTS (
                    SELECT 1
                    FROM ' . $table . ' ' . $alias . '
                    WHERE ' . $alias . '.examination_id = e.id
                      AND (' . implode(' OR ', $filled) . ')
                )';
    }

    private static function date(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $value : null;
    }

    private static function inputString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = self::inputString($value);
        return $value === '' ? null : $value;
    }
}
