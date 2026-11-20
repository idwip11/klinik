<?php

declare(strict_types=1);

final class ReferenceEvaluator
{
    public static function requiresReference(string $code): bool
    {
        return !in_array($code, ['BLOOD_GROUP', 'RHESUS', 'ANC_BLOOD_GROUP'], true);
    }

    /** @param array<string, mixed> $examination @return list<array<string, mixed>> */
    public static function evaluate(array $examination): array
    {
        if (isset($examination['id']) && ExaminationPanelCatalogRepository::forExamination((int) $examination['id']) !== []) {
            return self::evaluatePanelResults($examination);
        }

        $flags = [];
        $ageDays = self::ageDays($examination['birth_date'] ?? null, $examination['examined_on'] ?? null);
        $ageYears = self::ageYears($examination['birth_date'] ?? null, $examination['examined_on'] ?? null);
        $sex = in_array($examination['sex'] ?? null, ['L', 'P'], true) ? (string) $examination['sex'] : null;

        foreach (LabTestCatalog::all() as $testCode => $test) {
            $value = $examination[$test['field']] ?? null;
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $range = ReferenceRangeRepository::findApplicable($testCode, $sex, $ageDays, $ageYears);
            $flag = [
                'test_code' => $testCode,
                'display_name' => $test['label'],
                'section' => $test['section'],
                'raw_value' => trim((string) $value),
                'unit' => $range === false ? null : $range['unit'],
                'reference_range_id' => $range === false ? null : (int) $range['id'],
                'reference_sex' => $range === false ? null : (string) $range['sex'],
                'reference_text' => $range === false ? 'Belum dikonfigurasi' : self::referenceTextForTestCode($testCode, $range),
                'interpretation' => $range === false ? 'not_assessed' : self::interpret($test['kind'], (string) $value, $range),
            ];
            $flags[] = $flag;
        }

        return $flags;
    }

    /** @param array<string,mixed> $examination @return list<array<string,mixed>> */
    private static function evaluatePanelResults(array $examination): array
    {
        $flags = [];
        $ageDays = self::ageDays($examination['birth_date'] ?? null, $examination['examined_on'] ?? null);
        $ageYears = self::ageYears($examination['birth_date'] ?? null, $examination['examined_on'] ?? null);
        $sex = in_array($examination['sex'] ?? null, ['L', 'P'], true) ? (string) $examination['sex'] : null;
        foreach (ExaminationParameterResultRepository::forEvaluation((int) $examination['id']) as $result) {
            $kind = $result['result_type'] === 'numeric' ? 'numeric' : 'qualitative';
            $range = ReferenceRangeRepository::findApplicable((string) $result['code'], $sex, $ageDays, $ageYears);
            $flags[] = [
                'test_code' => (string) $result['code'],
                'display_name' => (string) $result['label'],
                'section' => $result['panel_code'] === 'urinalysis_complete' || str_starts_with((string) $result['code'], 'ANC_URINE_') ? 'urine' : 'blood',
                'raw_value' => (string) $result['value'],
                'unit' => $range === false ? $result['unit'] : $range['unit'],
                'reference_range_id' => $range === false ? null : (int) $range['id'],
                'reference_sex' => $range === false ? null : (string) $range['sex'],
                'reference_text' => $range === false ? 'Belum dikonfigurasi' : self::referenceTextForTestCode((string) $result['code'], $range),
                'interpretation' => $range === false
                    ? 'not_assessed'
                    : self::interpretPanelResult((string) $result['code'], $kind, (string) $result['value'], $range),
            ];
        }

        return $flags;
    }

    /** @param array<string, mixed> $range */
    private static function interpret(string $kind, string $value, array $range): string
    {
        if ($kind === 'numeric') {
            if (!is_numeric(str_replace(',', '.', $value))) {
                return 'not_assessed';
            }
            $number = (float) str_replace(',', '.', $value);
            if ($range['lower_bound'] !== null && (
                $number < (float) $range['lower_bound']
                || ($number === (float) $range['lower_bound'] && !(bool) ($range['lower_inclusive'] ?? true))
            )) {
                return 'low';
            }
            if ($range['upper_bound'] !== null && (
                $number > (float) $range['upper_bound']
                || ($number === (float) $range['upper_bound'] && !(bool) ($range['upper_inclusive'] ?? true))
            )) {
                return 'high';
            }

            return 'normal';
        }

        $normalValues = json_decode((string) ($range['normal_values'] ?? '[]'), true);
        if (!is_array($normalValues)) {
            return 'not_assessed';
        }
        $normalValues = array_map(static fn(mixed $item): string => mb_strtolower(trim((string) $item)), $normalValues);

        return in_array(mb_strtolower(trim($value)), $normalValues, true) ? 'normal' : 'abnormal';
    }

    /**
     * Menilai satu nilai terhadap aturan rujukan yang sudah dipilih oleh pemanggil.
     * Pemilihan aturan tetap menjadi tanggung jawab pemanggil agar kebijakan
     * penilaian yang digunakan dapat dinyatakan secara eksplisit.
     *
     * @param array<string, mixed> $range
     */
    public static function interpretValue(string $kind, string $value, array $range): string
    {
        return self::interpret($kind, $value, $range);
    }

    /** @param array<string, mixed> $range */
    public static function referenceText(array $range): string
    {
        if ($range['result_kind'] === 'qualitative') {
            $values = json_decode((string) ($range['normal_values'] ?? '[]'), true);
            return is_array($values) ? implode(' / ', $values) : 'Tidak tersedia';
        }

        $lower = $range['lower_bound'];
        $upper = $range['upper_bound'];
        $lowerInclusive = (bool) ($range['lower_inclusive'] ?? true);
        $upperInclusive = (bool) ($range['upper_inclusive'] ?? true);
        if ($lower !== null && $upper !== null) {
            if (!$lowerInclusive || !$upperInclusive) {
                return ($lowerInclusive ? '≥ ' : '> ') . self::trimNumber((string) $lower)
                    . ' dan ' . ($upperInclusive ? '≤ ' : '< ') . self::trimNumber((string) $upper);
            }
            return self::trimNumber((string) $lower) . ' – ' . self::trimNumber((string) $upper);
        }
        if ($lower !== null) {
            return ($lowerInclusive ? '≥ ' : '> ') . self::trimNumber((string) $lower);
        }
        if ($upper !== null) {
            return ($upperInclusive ? '≤ ' : '< ') . self::trimNumber((string) $upper);
        }

        return 'Tidak tersedia';
    }

    /** @param array<string, mixed> $range */
    private static function referenceTextForTestCode(string $testCode, array $range): string
    {
        if ($testCode === 'HBSAG_ECLIA') {
            return "≤ 0,9: Non Reaktif\n> 1: Reaktif";
        }

        if ($testCode === 'URINE_SG'
            && $range['result_kind'] === 'numeric'
            && (float) ($range['lower_bound'] ?? 0) === 1.01
            && (float) ($range['upper_bound'] ?? 0) === 1.03) {
            return '1,010 – 1,030';
        }

        return self::referenceText($range);
    }

    /** @param array<string, mixed> $range */
    private static function interpretPanelResult(string $testCode, string $kind, string $value, array $range): string
    {
        if ($testCode === 'HBSAG_ECLIA') {
            $normalised = str_replace(',', '.', trim($value));
            if (!is_numeric($normalised)) {
                return 'not_assessed';
            }

            $coi = (float) $normalised;
            if ($coi <= 0.9) {
                return 'normal';
            }
            if ($coi > 1.0) {
                return 'abnormal';
            }

            return 'not_assessed';
        }

        return self::interpret($kind, $value, $range);
    }

    /**
     * Menyaring format rujukan lama seperti "L: 13,4–17,7 P: 11,4–15,1"
     * ketika masih ditemukan pada data/snapshot lama.
     */
    public static function referenceTextForSex(mixed $referenceText, mixed $sex): string
    {
        $text = trim((string) ($referenceText ?? ''));
        if ($text === '' || !in_array($sex, ['L', 'P'], true)) {
            return self::localizeReferenceText($text);
        }

        preg_match_all(
            '/(?:^|[;,\\r\\n]|\\s)([LP])\\s*:\\s*(.*?)(?=(?:[;,\\r\\n]|\\s+)[LP]\\s*:|$)/iu',
            $text,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $match) {
            if (strtoupper($match[1]) === $sex) {
                return self::localizeReferenceText(trim($match[2]));
            }
        }

        return self::localizeReferenceText($text);
    }

    private static function localizeReferenceText(string $text): string
    {
        return preg_replace_callback(
            '/(?<![\p{L}\d])[\d]+(?:[.,][\d]+)?/u',
            static fn(array $match): string => View::formatNumber($match[0]),
            $text
        ) ?? $text;
    }

    private static function trimNumber(string $value): string
    {
        return View::formatNumber($value);
    }

    public static function ageDays(mixed $birthDate, mixed $examinedOn): ?int
    {
        if (!$birthDate || !$examinedOn) {
            return null;
        }
        try {
            $birth = new DateTimeImmutable((string) $birthDate);
            $exam = new DateTimeImmutable((string) $examinedOn);
        } catch (Throwable) {
            return null;
        }
        if ($exam < $birth) {
            return null;
        }

        return (int) $birth->diff($exam)->days;
    }

    public static function ageYears(mixed $birthDate, mixed $examinedOn): ?int
    {
        if (!$birthDate || !$examinedOn) {
            return null;
        }
        try {
            $birth = new DateTimeImmutable((string) $birthDate);
            $exam = new DateTimeImmutable((string) $examinedOn);
        } catch (Throwable) {
            return null;
        }
        if ($exam < $birth) {
            return null;
        }

        return (int) $birth->diff($exam)->y;
    }

    public static function placeholderForTestCode(string $testCode, ?string $sex = null, ?int $ageDays = null, ?int $ageYears = null): ?string
    {
        static $curated = [
            'RBC' => '4,5',
            'MCV' => '82,6',
            'MCH' => '30,2',
            'MCHC' => '36,6',
            'URINE_SG' => '1,015',
            'URINE_PH' => '6,0',
            'EOSINOPHIL' => '2',
            'BASOPHIL' => '0',
            'SGOT' => '20',
            'SGPT' => '20',
            'GDS' => '100',
            'CHOL' => '170',
            'GLUCOSE_2H_PP' => '110',
            'CREATININE' => '0,8',
            'HBSAG_ECLIA' => '0,10',
            'TRIGLYCERIDES' => '120',
            'HDL' => '50',
            'LDL' => '90',
            'HBA1C' => '5,4',
            'MALARIA_RAPID' => 'Negatif',
            'MALARIA_PAN' => 'Negatif',
            'MALARIA_PF' => 'Negatif',
        ];

        if (isset($curated[$testCode])) {
            return 'Contoh: ' . $curated[$testCode];
        }

        $range = ReferenceRangeRepository::findApplicable($testCode, $sex, $ageDays, $ageYears);
        if ($range === false && $sex !== null) {
            $range = ReferenceRangeRepository::findApplicable($testCode, null, $ageDays, $ageYears);
            if ($range === false) {
                $range = ReferenceRangeRepository::findApplicable($testCode, $sex === 'P' ? 'L' : 'P', $ageDays, $ageYears);
            }
        }

        if ($range === false) {
            return null;
        }

        if ($range['result_kind'] === 'qualitative') {
            $normals = json_decode((string) ($range['normal_values'] ?? '[]'), true);
            if (is_array($normals) && isset($normals[0]) && trim((string) $normals[0]) !== '') {
                return 'Contoh: ' . trim((string) $normals[0]);
            }
            return null;
        }

        if ($range['lower_bound'] !== null) {
            $lower = (float) $range['lower_bound'];
            if ($lower > 0) {
                return 'Contoh: ' . View::formatNumber($range['lower_bound']);
            }
            if ($range['upper_bound'] !== null) {
                $upper = (float) $range['upper_bound'];
                $mid = ($lower + $upper) / 2;
                return 'Contoh: ' . View::formatNumber((string) round($mid));
            }
        }

        if ($range['upper_bound'] !== null) {
            $upper = (float) $range['upper_bound'];
            $safe = $upper * 0.75;
            return 'Contoh: ' . View::formatNumber((string) round($safe));
        }

        return null;
    }
}
