<?php

declare(strict_types=1);

final class HistoricalReferenceEvaluator
{
    /**
     * Menilai kumpulan hasil lintas pasien dengan aturan rujukan aktif saat ini.
     * Metode ini dipakai dashboard agregat dan sengaja tidak mengembalikan
     * identitas pasien pada hasil akhirnya.
     *
     * @param list<array{id:int,patient_id:int,examined_on:string,sex:?string,birth_date:?string}> $examinations
     * @param array<int,array<string,array<string,mixed>>> $results
     * @return array<int,array<string,array<string,mixed>>>
     */
    public static function annotatePopulation(PDO $pdo, array $examinations, array $results): array
    {
        if ($examinations === [] || $results === []) {
            return $results;
        }

        $codes = [];
        foreach ($results as $resultSet) {
            foreach (array_keys($resultSet) as $code) {
                $codes[$code] = true;
            }
        }
        $ranges = $codes === [] ? [] : self::currentRanges($pdo, array_keys($codes));

        foreach ($examinations as $examination) {
            $examinationId = (int) $examination['id'];
            if (!isset($results[$examinationId])) {
                continue;
            }

            $sex = in_array($examination['sex'] ?? null, ['L', 'P'], true) ? (string) $examination['sex'] : null;
            $birthDate = self::nullableString($examination['birth_date'] ?? null);
            $ageDays = self::age($birthDate, (string) $examination['examined_on'], 'days');
            $ageYears = self::age($birthDate, (string) $examination['examined_on'], 'years');

            foreach ($results[$examinationId] as $code => $result) {
                if (!ReferenceEvaluator::requiresReference($code)) {
                    $results[$examinationId][$code]['interpretation'] = 'not_assessed';
                    $results[$examinationId][$code]['reference_text'] = '';
                    $results[$examinationId][$code]['reference_range_id'] = null;
                    $results[$examinationId][$code]['unassessed_reason'] = 'not_applicable';
                    continue;
                }
                $range = isset($ranges[$code])
                    ? self::applicableRange($ranges[$code], $sex, $ageDays, $ageYears)
                    : null;
                if ($range === null) {
                    $results[$examinationId][$code]['interpretation'] = 'not_assessed';
                    $results[$examinationId][$code]['unassessed_reason'] = 'no_active_reference';
                    continue;
                }

                $interpretation = ReferenceEvaluator::interpretValue(
                    !empty($result['numeric']) ? 'numeric' : 'qualitative',
                    (string) $result['value'],
                    $range
                );
                $results[$examinationId][$code]['interpretation'] = $interpretation;
                $results[$examinationId][$code]['reference_text'] = ReferenceEvaluator::referenceText($range);
                $results[$examinationId][$code]['reference_range_id'] = (int) $range['id'];
                $results[$examinationId][$code]['assessment_source'] = 'current_rule';
                $results[$examinationId][$code]['unassessed_reason'] = $interpretation === 'not_assessed'
                    ? 'not_interpretable'
                    : null;
            }
        }

        return $results;
    }

    /**
     * Menambahkan interpretasi pada matriks hasil kumulatif tanpa mengubah
     * hasil pemeriksaan yang tersimpan.
     *
     * Nilai rujukan yang digunakan adalah aturan yang sedang aktif saat ini.
     * Kebijakan ini membuat seluruh riwayat pasien dapat dibandingkan dengan
     * satu acuan yang konsisten, termasuk pemeriksaan yang sudah lama.
     *
     * @param list<array{id:int,lab_number:string,examination_type:string,status:string,examined_on:string,examined_time:?string}> $examinations
     * @param array<int,array<string,array{value:string,numeric:bool,unit:?string,source:string,interpretation:?string,reference_text:?string,reference_range_id:?int,assessment_source:?string}>> $results
     * @return array<int,array<string,array{value:string,numeric:bool,unit:?string,source:string,interpretation:?string,reference_text:?string,reference_range_id:?int,assessment_source:?string}>>
     */
    public static function annotate(PDO $pdo, int $patientId, array $examinations, array $results): array
    {
        if ($examinations === [] || $results === []) {
            return $results;
        }

        $patient = self::patient($pdo, $patientId);
        /** @var array<string,true> $codesNeedingRanges */
        $codesNeedingRanges = [];
        foreach ($results as $resultSet) {
            foreach (array_keys($resultSet) as $code) {
                $codesNeedingRanges[$code] = true;
            }
        }

        if ($patient === null || $codesNeedingRanges === []) {
            return $results;
        }

        $ranges = self::currentRanges($pdo, array_keys($codesNeedingRanges));
        foreach ($examinations as $examination) {
            $examinationId = $examination['id'];
            if (!isset($results[$examinationId])) {
                continue;
            }

            $ageDays = self::age($patient['birth_date'], $examination['examined_on'], 'days');
            $ageYears = self::age($patient['birth_date'], $examination['examined_on'], 'years');

            foreach ($results[$examinationId] as $code => $result) {
                if (!ReferenceEvaluator::requiresReference($code)) {
                    $results[$examinationId][$code]['interpretation'] = 'not_assessed';
                    $results[$examinationId][$code]['reference_text'] = '';
                    $results[$examinationId][$code]['reference_range_id'] = null;
                    $results[$examinationId][$code]['unassessed_reason'] = 'not_applicable';
                    continue;
                }
                if (!isset($ranges[$code])) {
                    continue;
                }

                $range = self::applicableRange(
                    $ranges[$code],
                    $patient['sex'],
                    $ageDays,
                    $ageYears
                );
                if ($range === null) {
                    continue;
                }

                $interpretation = ReferenceEvaluator::interpretValue(
                    $result['numeric'] ? 'numeric' : 'qualitative',
                    $result['value'],
                    $range
                );
                if ($interpretation === 'not_assessed') {
                    continue;
                }

                $results[$examinationId][$code]['interpretation'] = $interpretation;
                $results[$examinationId][$code]['reference_text'] = ReferenceEvaluator::referenceText($range);
                $results[$examinationId][$code]['reference_range_id'] = (int) $range['id'];
                $results[$examinationId][$code]['assessment_source'] = 'current_rule';
            }
        }

        return $results;
    }

    /** @return array{sex:?string,birth_date:?string}|null */
    private static function patient(PDO $pdo, int $patientId): ?array
    {
        $statement = $pdo->prepare('SELECT sex, birth_date FROM patients WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $patientId]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'sex' => in_array($row['sex'] ?? null, ['L', 'P'], true) ? (string) $row['sex'] : null,
            'birth_date' => self::nullableString($row['birth_date'] ?? null),
        ];
    }

    /**
     * @param list<string> $codes
     * @return array<string,list<array<string,mixed>>>
     */
    private static function currentRanges(PDO $pdo, array $codes): array
    {
        [$placeholders, $bindings] = self::bindings($codes, 'range_code');
        $statement = $pdo->prepare(
            'SELECT id, test_code, result_kind, unit, sex,
                    min_age_days, max_age_days, min_age_years, max_age_years,
                    lower_bound, upper_bound, lower_inclusive, upper_inclusive,
                    normal_values, version, approved_at
             FROM reference_ranges
             WHERE approval_status = "approved"
               AND is_active = 1
               AND approved_at IS NOT NULL
               AND test_code IN (' . implode(', ', $placeholders) . ')
             ORDER BY test_code, approved_at DESC, version DESC, id DESC'
        );
        $statement->execute($bindings);

        $ranges = [];
        foreach ($statement->fetchAll() as $row) {
            $code = strtoupper(trim((string) $row['test_code']));
            if ($code !== '') {
                $ranges[$code][] = $row;
            }
        }

        return $ranges;
    }

    /**
     * @param list<array<string,mixed>> $ranges
     * @return array<string,mixed>|null
     */
    private static function applicableRange(array $ranges, ?string $sex, ?int $ageDays, ?int $ageYears): ?array
    {
        $matches = [];
        foreach ($ranges as $range) {
            if ($range['sex'] !== 'ALL' && ($sex === null || $range['sex'] !== $sex)) {
                continue;
            }
            if (!self::ageMatches($range, $ageDays, $ageYears)) {
                continue;
            }

            $matches[] = $range;
        }
        if ($matches === []) {
            return null;
        }

        usort($matches, static function (array $left, array $right) use ($sex): int {
            $leftExactSex = $sex !== null && $left['sex'] === $sex ? 1 : 0;
            $rightExactSex = $sex !== null && $right['sex'] === $sex ? 1 : 0;
            $leftAgeSpecific = self::ageSpecific($left) ? 1 : 0;
            $rightAgeSpecific = self::ageSpecific($right) ? 1 : 0;

            return $rightExactSex <=> $leftExactSex
                ?: $rightAgeSpecific <=> $leftAgeSpecific
                ?: strcmp((string) $right['approved_at'], (string) $left['approved_at'])
                ?: (int) $right['version'] <=> (int) $left['version']
                ?: (int) $right['id'] <=> (int) $left['id'];
        });

        return $matches[0];
    }

    /** @param array<string,mixed> $range */
    private static function ageMatches(array $range, ?int $ageDays, ?int $ageYears): bool
    {
        if ($range['min_age_years'] !== null || $range['max_age_years'] !== null) {
            return $ageYears !== null
                && ($range['min_age_years'] === null || $ageYears >= (int) $range['min_age_years'])
                && ($range['max_age_years'] === null || $ageYears <= (int) $range['max_age_years']);
        }
        if ($range['min_age_days'] !== null || $range['max_age_days'] !== null) {
            return $ageDays !== null
                && ($range['min_age_days'] === null || $ageDays >= (int) $range['min_age_days'])
                && ($range['max_age_days'] === null || $ageDays <= (int) $range['max_age_days']);
        }

        return true;
    }

    /** @param array<string,mixed> $range */
    private static function ageSpecific(array $range): bool
    {
        return $range['min_age_days'] !== null
            || $range['max_age_days'] !== null
            || $range['min_age_years'] !== null
            || $range['max_age_years'] !== null;
    }

    private static function age(?string $birthDate, string $examinedOn, string $unit): ?int
    {
        if ($birthDate === null) {
            return null;
        }
        try {
            $birth = new DateTimeImmutable($birthDate);
            $exam = new DateTimeImmutable($examinedOn);
        } catch (Throwable) {
            return null;
        }
        if ($exam < $birth) {
            return null;
        }

        $difference = $birth->diff($exam);
        return $unit === 'years' ? (int) $difference->y : (int) $difference->days;
    }

    /**
     * @param list<int|string> $values
     * @return array{0:list<string>,1:array<string,int|string>}
     */
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

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
