<?php

declare(strict_types=1);

final class ReferenceRangeRepository
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return Database::connection()->query(
            'SELECT r.*, approver.full_name AS approved_by_name,
                    creator.full_name AS created_by_name, reviewer.full_name AS reviewed_by_name
             FROM reference_ranges r
             LEFT JOIN users approver ON approver.id = r.approved_by
             INNER JOIN users creator ON creator.id = r.created_by
             LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
             ORDER BY FIELD(r.approval_status, "draft", "approved", "rejected"),
                      r.is_active DESC, r.section, r.display_name, r.version DESC'
        )->fetchAll();
    }

    /** @return array<string, array<string, mixed>> */
    public static function catalog(): array
    {
        $catalog = [];
        foreach (ExaminationPanelCatalogRepository::all() as $panel) {
            foreach ($panel['parameters'] as $parameter) {
                $catalog[(string) $parameter['code']] = [
                    'field' => $parameter['legacy_field'],
                    'label' => (string) $parameter['label'],
                    'section' => $panel['code'] === 'urinalysis_complete' || str_starts_with((string) $parameter['code'], 'ANC_URINE_') ? 'urine' : 'blood',
                    'required_sex' => $panel['required_sex'] ?? null,
                    'kind' => $parameter['result_type'] === 'numeric' ? 'numeric' : 'qualitative',
                    'unit' => $parameter['unit'],
                    'panel_name' => (string) $panel['name'],
                    'panel_order' => (int) $panel['display_order'],
                    'display_order' => (int) $parameter['display_order'],
                ];
            }
        }

        foreach (LabTestCatalog::all() as $code => $test) {
            if (isset($catalog[$code])) {
                continue;
            }
            $catalog[$code] = array_merge($test, [
                'unit' => null,
                'panel_name' => 'Pemeriksaan lama',
                'panel_order' => 999,
                'display_order' => 999,
            ]);
        }

        uasort($catalog, static fn (array $left, array $right): int =>
            [$left['panel_order'], $left['display_order'], $left['label']]
            <=> [$right['panel_order'], $right['display_order'], $right['label']]
        );

        return $catalog;
    }

    /** @return array<string, mixed>|false */
    public static function findApplicable(string $testCode, ?string $sex, ?int $ageDays, ?int $ageYears = null): array|false
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM reference_ranges
             WHERE test_code = :test_code
               AND is_active = 1
               AND approval_status = "approved"
               AND (sex = "ALL" OR sex = :sex)
               AND (
                    (
                        min_age_years IS NULL AND max_age_years IS NULL
                        AND (min_age_days IS NULL OR :age_days_min IS NOT NULL AND min_age_days <= :age_days_min_compare)
                        AND (max_age_days IS NULL OR :age_days_max IS NOT NULL AND max_age_days >= :age_days_max_compare)
                    )
                    OR
                    (
                        (min_age_years IS NOT NULL OR max_age_years IS NOT NULL)
                        AND (min_age_years IS NULL OR :age_years_min IS NOT NULL AND min_age_years <= :age_years_min_compare)
                        AND (max_age_years IS NULL OR :age_years_max IS NOT NULL AND max_age_years >= :age_years_max_compare)
                    )
               )
             ORDER BY (sex = :preferred_sex) DESC,
                      (min_age_years IS NOT NULL OR max_age_years IS NOT NULL OR min_age_days IS NOT NULL OR max_age_days IS NOT NULL) DESC,
                      version DESC
             LIMIT 1'
        );
        $statement->execute([
            'test_code' => $testCode,
            'sex' => $sex ?? '',
            'age_days_min' => $ageDays,
            'age_days_min_compare' => $ageDays,
            'age_days_max' => $ageDays,
            'age_days_max_compare' => $ageDays,
            'age_years_min' => $ageYears,
            'age_years_min_compare' => $ageYears,
            'age_years_max' => $ageYears,
            'age_years_max_compare' => $ageYears,
            'preferred_sex' => $sex ?? '',
        ]);

        return $statement->fetch();
    }

    /** @param array<string, mixed> $input */
    public static function createDraft(array $input, int $userId): int
    {
        $testCode = trim((string) ($input['test_code'] ?? ''));
        $catalog = self::catalog()[$testCode] ?? null;
        if ($catalog === null) {
            throw new ValidationException(['form' => 'Parameter pemeriksaan tidak valid.']);
        }

        $population = (string) ($input['sex'] ?? $input['population'] ?? 'ALL');
        $sex = $population === 'CHILD' ? 'ALL' : $population;
        if (!in_array($sex, ['ALL', 'L', 'P'], true)) {
            throw new ValidationException(['form' => 'Jenis kelamin sasaran tidak valid.']);
        }
        if (!empty($catalog['required_sex']) && $sex !== $catalog['required_sex']) {
            throw new ValidationException(['form' => 'Nilai normal panel ANC ibu hamil harus khusus perempuan.']);
        }
        $usesAgeYears = array_key_exists('min_age_years', $input) || array_key_exists('max_age_years', $input);
        $minAgeDays = $usesAgeYears
            ? null
            : self::nullableInteger($input['min_age_days'] ?? ($population === 'CHILD' ? 0 : null), 'Usia minimum');
        $maxAgeDays = $usesAgeYears
            ? null
            : self::nullableInteger($input['max_age_days'] ?? ($population === 'CHILD' ? 6574 : null), 'Usia maksimum');
        $minAgeYears = $usesAgeYears
            ? self::nullableInteger($input['min_age_years'] ?? null, 'Usia minimum (tahun)', 150)
            : null;
        $maxAgeYears = $usesAgeYears
            ? self::nullableInteger($input['max_age_years'] ?? null, 'Usia maksimum (tahun)', 150)
            : null;
        if (($maxAgeDays !== null && $minAgeDays !== null && $maxAgeDays < $minAgeDays)
            || ($maxAgeYears !== null && $minAgeYears !== null && $maxAgeYears < $minAgeYears)) {
            throw new ValidationException(['form' => 'Usia maksimum tidak boleh lebih kecil dari usia minimum.']);
        }

        $referenceValue = trim((string) ($input['reference_value'] ?? ''));
        if ($catalog['kind'] === 'numeric' && $referenceValue !== '') {
            [$lower, $upper, $lowerInclusive, $upperInclusive] = self::parseReferenceValue($referenceValue);
        } elseif ($catalog['kind'] === 'numeric') {
            // Backward compatibility for previously submitted forms/scripts.
            $lower = self::nullableDecimal($input['lower_bound'] ?? null, 'Batas bawah');
            $upper = self::nullableDecimal($input['upper_bound'] ?? null, 'Batas atas');
            $lowerInclusive = isset($input['lower_inclusive']) ? 1 : 0;
            $upperInclusive = isset($input['upper_inclusive']) ? 1 : 0;
        } else {
            $lower = null;
            $upper = null;
            $lowerInclusive = 1;
            $upperInclusive = 1;
        }
        $normalValues = self::normalValues($input['normal_values'] ?? null);
        if ($catalog['kind'] === 'numeric' && $lower === null && $upper === null) {
            throw new ValidationException(['form' => 'Parameter numerik memerlukan batas bawah dan/atau batas atas.']);
        }
        if ($catalog['kind'] === 'numeric' && $lower !== null && $upper !== null && (float) $lower > (float) $upper) {
            throw new ValidationException(['form' => 'Batas bawah tidak boleh melebihi batas atas.']);
        }
        if ($catalog['kind'] === 'qualitative' && $normalValues === null) {
            throw new ValidationException(['form' => 'Parameter kualitatif memerlukan minimal satu nilai yang dianggap normal.']);
        }
        $unit = self::nullableText($input['unit'] ?? $catalog['unit'] ?? null, 50);
        $note = self::nullableText($input['reference_note'] ?? null, 255);
        if ($note === null) {
            throw new ValidationException(['form' => 'Sumber atau catatan persetujuan klinis wajib diisi.']);
        }
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            self::assertNoConflictingActiveScope($pdo, $testCode, $sex, $minAgeDays, $maxAgeDays, $minAgeYears, $maxAgeYears);
            $lockStatement = $pdo->prepare('SELECT id FROM reference_ranges WHERE test_code = :test_code FOR UPDATE');
            $lockStatement->execute(['test_code' => $testCode]);
            $versionStatement = $pdo->prepare('SELECT COALESCE(MAX(version), 0) + 1 FROM reference_ranges WHERE test_code = :test_code');
            $versionStatement->execute(['test_code' => $testCode]);
            $version = (int) $versionStatement->fetchColumn();

            $statement = $pdo->prepare(
                'INSERT INTO reference_ranges
                    (test_code, display_name, section, result_kind, unit, sex, min_age_days, max_age_days, min_age_years, max_age_years,
                     lower_bound, upper_bound, lower_inclusive, upper_inclusive, normal_values, reference_note,
                     version, is_active, approval_status, created_by, submitted_at, approved_by, approved_at, created_at)
                 VALUES
                    (:test_code, :display_name, :section, :result_kind, :unit, :sex, :min_age_days, :max_age_days, :min_age_years, :max_age_years,
                     :lower_bound, :upper_bound, :lower_inclusive, :upper_inclusive, :normal_values, :reference_note,
                     :version, 0, "draft", :created_by, UTC_TIMESTAMP(), NULL, NULL, UTC_TIMESTAMP())'
            );
            $statement->execute([
                'test_code' => $testCode,
                'display_name' => $catalog['label'],
                'section' => $catalog['section'],
                'result_kind' => $catalog['kind'],
                'unit' => $unit,
                'sex' => $sex,
                'min_age_days' => $minAgeDays,
                'max_age_days' => $maxAgeDays,
                'min_age_years' => $minAgeYears,
                'max_age_years' => $maxAgeYears,
                'lower_bound' => $catalog['kind'] === 'numeric' ? $lower : null,
                'upper_bound' => $catalog['kind'] === 'numeric' ? $upper : null,
                'lower_inclusive' => $catalog['kind'] === 'numeric' ? $lowerInclusive : 1,
                'upper_inclusive' => $catalog['kind'] === 'numeric' ? $upperInclusive : 1,
                'normal_values' => $catalog['kind'] === 'qualitative' ? $normalValues : null,
                'reference_note' => $note,
                'version' => $version,
                'created_by' => $userId,
            ]);
            $id = (int) $pdo->lastInsertId();
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        AuditLogger::record('reference_range.draft_created', $userId, 'reference_range', $id, null, ['test_code' => $testCode, 'version' => $version]);

        return $id;
    }

    /**
     * Saves a new reference rule and makes it active in one transaction.
     *
     * The draft/review methods remain available solely to preserve historical
     * data and the audit trail, but the current admin workflow saves directly
     * after the clinic has confirmed the source note.
     *
     * @param array<string, mixed> $input
     */
    public static function create(array $input, int $userId): int
    {
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $id = self::createDraft($input, $userId);
            self::approve($id, $userId, '1');
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return $id;
    }

    public static function approve(int $id, int $userId, mixed $clinicalApproval): void
    {
        if ((string) $clinicalApproval !== '1') {
            throw new ValidationException(['form' => 'Konfirmasi persetujuan tenaga klinis wajib dicentang sebelum aktivasi.']);
        }

        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $statement = $pdo->prepare('SELECT * FROM reference_ranges WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $id]);
            $range = $statement->fetch();
            if ($range === false || $range['approval_status'] !== 'draft') {
                throw new ValidationException(['form' => 'Draft nilai rujukan tidak ditemukan atau sudah ditinjau.']);
            }

            self::assertNoConflictingActiveScope(
                $pdo,
                (string) $range['test_code'],
                (string) $range['sex'],
                $range['min_age_days'] === null ? null : (int) $range['min_age_days'],
                $range['max_age_days'] === null ? null : (int) $range['max_age_days'],
                $range['min_age_years'] === null ? null : (int) $range['min_age_years'],
                $range['max_age_years'] === null ? null : (int) $range['max_age_years']
            );

            $deactivate = $pdo->prepare(
                'UPDATE reference_ranges
                 SET is_active = 0
                 WHERE id <> :id AND test_code = :test_code AND sex = :sex
                   AND min_age_days <=> :min_age_days AND max_age_days <=> :max_age_days
                   AND min_age_years <=> :min_age_years AND max_age_years <=> :max_age_years
                   AND approval_status = "approved" AND is_active = 1'
            );
            $deactivate->execute([
                'id' => $id,
                'test_code' => $range['test_code'],
                'sex' => $range['sex'],
                'min_age_days' => $range['min_age_days'],
                'max_age_days' => $range['max_age_days'],
                'min_age_years' => $range['min_age_years'],
                'max_age_years' => $range['max_age_years'],
            ]);

            $pdo->prepare(
                'UPDATE reference_ranges
                 SET approval_status = "approved", is_active = 1,
                     approved_by = :approved_by, approved_at = UTC_TIMESTAMP(),
                     reviewed_by = :reviewed_by, reviewed_at = UTC_TIMESTAMP(), review_note = NULL
                 WHERE id = :id'
            )->execute(['approved_by' => $userId, 'reviewed_by' => $userId, 'id' => $id]);
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        AuditLogger::record('reference_range.approved', $userId, 'reference_range', $id, null, ['clinical_approval_confirmed' => true]);
    }

    public static function reject(int $id, int $userId, mixed $reviewNote): void
    {
        $note = self::nullableText($reviewNote, 255);
        if ($note === null) {
            throw new ValidationException(['form' => 'Catatan penolakan wajib diisi.']);
        }

        $statement = Database::connection()->prepare(
            'UPDATE reference_ranges
             SET approval_status = "rejected", is_active = 0,
                 reviewed_by = :user_id, reviewed_at = UTC_TIMESTAMP(), review_note = :review_note
             WHERE id = :id AND approval_status = "draft"'
        );
        $statement->execute(['user_id' => $userId, 'review_note' => $note, 'id' => $id]);
        if ($statement->rowCount() !== 1) {
            throw new ValidationException(['form' => 'Draft nilai rujukan tidak ditemukan atau sudah ditinjau.']);
        }

        AuditLogger::record('reference_range.rejected', $userId, 'reference_range', $id, null, ['review_note' => $note]);
    }

    private static function assertNoConflictingActiveScope(
        PDO $pdo,
        string $testCode,
        string $sex,
        ?int $minAgeDays,
        ?int $maxAgeDays,
        ?int $minAgeYears,
        ?int $maxAgeYears
    ): void {
        $statement = $pdo->prepare(
            'SELECT id, min_age_days, max_age_days, min_age_years, max_age_years
             FROM reference_ranges
             WHERE test_code = :test_code AND sex = :sex
               AND approval_status = "approved" AND is_active = 1'
        );
        $statement->execute(['test_code' => $testCode, 'sex' => $sex]);
        $newUsesYears = $minAgeYears !== null || $maxAgeYears !== null;
        $newIsFallback = !$newUsesYears && $minAgeDays === null && $maxAgeDays === null;
        foreach ($statement->fetchAll() as $existing) {
            $existingUsesYears = $existing['min_age_years'] !== null || $existing['max_age_years'] !== null;
            $existingIsFallback = !$existingUsesYears && $existing['min_age_days'] === null && $existing['max_age_days'] === null;
            if ($existingIsFallback || $newIsFallback) {
                continue;
            }
            if ($existingUsesYears !== $newUsesYears) {
                throw new ValidationException([
                    'form' => 'Aturan usia berbasis hari dan tahun tidak boleh aktif bersamaan pada parameter serta jenis kelamin yang sama. Buat versi pengganti atau arsipkan aturan lama terlebih dahulu.',
                ]);
            }

            $existingMinimum = $existingUsesYears ? $existing['min_age_years'] : $existing['min_age_days'];
            $existingMaximum = $existingUsesYears ? $existing['max_age_years'] : $existing['max_age_days'];
            $newMinimum = $newUsesYears ? $minAgeYears : $minAgeDays;
            $newMaximum = $newUsesYears ? $maxAgeYears : $maxAgeDays;
            $overlaps = ($existingMaximum === null || $newMinimum === null || (int) $existingMaximum >= $newMinimum)
                && ($newMaximum === null || $existingMinimum === null || (int) $existingMinimum <= $newMaximum);
            $sameMinimum = $existingMinimum === null ? $newMinimum === null : $newMinimum !== null && (int) $existingMinimum === $newMinimum;
            $sameMaximum = $existingMaximum === null ? $newMaximum === null : $newMaximum !== null && (int) $existingMaximum === $newMaximum;
            if ($overlaps && (!$sameMinimum || !$sameMaximum)) {
                throw new ValidationException([
                    'form' => 'Rentang usia bertumpang tindih dengan aturan aktif untuk parameter dan jenis kelamin yang sama. Aturan semua usia boleh menjadi fallback, tetapi dua rentang usia khusus tidak boleh tumpang tindih.',
                ]);
            }
        }
    }

    private static function nullableText(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new ValidationException(['form' => 'Salah satu nilai teks terlalu panjang.']);
        }

        return $value;
    }

    private static function nullableInteger(mixed $value, string $label, int $maximum = 50000): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $maximum]]);
        if ($number === false) {
            throw new ValidationException(['form' => $label . ' tidak valid.']);
        }

        return (int) $number;
    }

    private static function nullableDecimal(mixed $value, string $label): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new ValidationException(['form' => $label . ' tidak valid.']);
        }

        return number_format((float) $value, 6, '.', '');
    }

    /** @return array{0: ?string, 1: ?string, 2: int, 3: int} */
    private static function parseReferenceValue(string $value): array
    {
        $value = str_replace(['–', '—', '−'], '-', trim($value));

        if (preg_match('/^(?:<=|<|≤)\s*(.+)$/u', $value, $matches) === 1) {
            $inclusive = str_starts_with($value, '≤') || str_starts_with($value, '<=') ? 1 : 0;

            return [null, self::referenceNumber($matches[1]), 1, $inclusive];
        }
        if (preg_match('/^(?:>=|>|≥)\s*(.+)$/u', $value, $matches) === 1) {
            $inclusive = str_starts_with($value, '≥') || str_starts_with($value, '>=') ? 1 : 0;

            return [self::referenceNumber($matches[1]), null, $inclusive, 1];
        }
        if (preg_match('/^(.+?)\s*-\s*(.+)$/', $value, $matches) === 1) {
            return [self::referenceNumber($matches[1]), self::referenceNumber($matches[2]), 1, 1];
        }
        if ($value !== '') {
            $number = self::referenceNumber($value);

            return [$number, $number, 1, 1];
        }

        throw new ValidationException(['form' => 'Format nilai rujukan tidak valid. Contoh: 11,4 - 15,1 atau < 200.']);
    }

    private static function referenceNumber(string $value): string
    {
        $value = str_replace(' ', '', trim($value));
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') === 1 && preg_match('/\.\d{3}$/', $value) === 1) {
            $value = str_replace('.', '', $value);
        }
        if ($value === '' || !is_numeric($value)) {
            throw new ValidationException(['form' => 'Angka pada nilai rujukan tidak valid.']);
        }

        return number_format((float) $value, 6, '.', '');
    }

    private static function normalValues(mixed $value): ?string
    {
        $items = preg_split('/[\r\n,|]+/', (string) $value) ?: [];
        $items = array_values(array_unique(array_filter(array_map(static fn(string $item): string => trim($item), $items))));
        if ($items === []) {
            return null;
        }

        return json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
