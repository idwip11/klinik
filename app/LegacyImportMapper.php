<?php

declare(strict_types=1);

final class LegacyImportMapper
{
    /** @var list<array{field:string,code:string,message:string}> */
    private array $errors = [];

    /** @var list<array{field:string,code:string,message:string}> */
    private array $warnings = [];

    /**
     * @param array<string, string|null> $row
     * @return array<string, mixed>
     */
    public function map(array $row, string $type): array
    {
        $this->errors = [];
        $this->warnings = [];
        $row = $this->normalizeRowKeys($row);

        if (!in_array($type, ['mcu_general', 'drug_screen'], true)) {
            throw new InvalidArgumentException('Jenis impor tidak didukung.');
        }

        $birth = $this->birthDetails($this->value($row, $type === 'drug_screen' ? ['tanggal lahir'] : ['ttl']));

        $patient = [
            'medical_record_number' => $this->requiredText($this->value($row, ['no rm', 'no. rm']), 'medical_record_number', 50),
            'national_id' => $this->text($this->value($row, ['nik']), 'national_id', 32),
            'full_name' => $this->requiredText($this->value($row, ['nama']), 'full_name', 150),
            'birth_place' => $this->text($birth['place'], 'birth_place', 100),
            'birth_date' => $birth['date'],
            'sex' => $this->sex($this->value($row, ['l/p'])),
            'address' => $this->text($this->value($row, ['alamat lengkap', 'alamat']), 'address', 1000),
            'phone' => $this->text($this->value($row, ['no. hp', 'no hp']), 'phone', 30),
            'occupation' => $this->text($this->value($row, ['pekerjaan']), 'occupation', 150),
        ];

        $examinedOn = $this->date($this->value($row, ['tanggal periksa']), 'examined_on', true);
        $examination = [
            'lab_number' => $this->requiredText($this->value($row, ['no. lab', 'nomor lab', 'no lab']), 'lab_number', 50),
            'examination_type' => $type,
            'examined_on' => $examinedOn,
            'examined_time' => $this->time($this->value($row, ['jam periksa'])),
            'condition_note' => $this->text($this->value($row, ['kondisi']), 'condition_note', 500),
            'letter_number' => $this->text($this->value($row, ['no. surat', 'no surat']), 'letter_number', 100),
            'purpose' => $this->text($this->value($row, ['keperluan']), 'purpose', 255),
        ];

        $mapped = [
            'patient' => $patient,
            'examination' => $examination,
            'vital' => [],
            'blood' => [],
            'urine' => [],
            'widal' => [],
            'drug' => [],
        ];

        if ($type === 'mcu_general') {
            $mapped = array_replace($mapped, $this->mapMcu($row));
        } else {
            $mapped['drug'] = $this->mapDrug($row);
        }

        $mapped['errors'] = $this->errors;
        $mapped['warnings'] = $this->warnings;

        return $mapped;
    }

    /** @param array<string, string|null> $row @return array<string, array<string, mixed>> */
    private function mapMcu(array $row): array
    {
        [$systolic, $diastolic] = $this->bloodPressure($this->value($row, ['td']));

        return [
            'vital' => [
                'blood_pressure_systolic' => $systolic,
                'blood_pressure_diastolic' => $diastolic,
                'height_cm' => $this->decimal($this->value($row, ['tb']), 'height_cm'),
                'weight_kg' => $this->decimal($this->value($row, ['bb']), 'weight_kg'),
                'respiratory_rate' => $this->decimal($this->value($row, ['rr']), 'respiratory_rate'),
                'color_vision' => $this->text($this->value($row, ['buta warna']), 'color_vision', 50),
                'pulse_rate' => $this->decimal($this->value($row, ['nadi']), 'pulse_rate'),
                'blood_group' => $this->bloodGroup($this->value($row, ['goldar'])),
                'rhesus' => $this->rhesus($this->value($row, ['rhesus'])),
                'temperature_c' => $this->decimal($this->value($row, ['suhu']), 'temperature_c'),
            ],
            'blood' => [
                'hgb' => $this->decimal($this->value($row, ['hgb']), 'hgb'),
                'wbc' => $this->decimal($this->value($row, ['wbc']), 'wbc'),
                'plt' => $this->decimal($this->value($row, ['plt']), 'plt'),
                'rbc' => $this->decimal($this->value($row, ['rbc']), 'rbc'),
                'hct' => $this->decimal($this->value($row, ['hct']), 'hct'),
                'lymph_pct' => $this->decimal($this->value($row, ['lymph%', 'lymph']), 'lymph_pct'),
                'mid_pct' => $this->decimal($this->value($row, ['mid%']), 'mid_pct'),
                'gran_pct' => $this->decimal($this->value($row, ['gran%']), 'gran_pct'),
                'mcv' => $this->decimal($this->value($row, ['mcv']), 'mcv'),
                'mch' => $this->decimal($this->value($row, ['mch']), 'mch'),
                'mchc' => $this->decimal($this->value($row, ['mchc']), 'mchc'),
                'eosinophil' => $this->decimal($this->value($row, ['eosinofil', 'eosinophil']), 'eosinophil'),
                'basophil' => $this->decimal($this->value($row, ['basofil', 'basophil']), 'basophil'),
                'neutrophil' => $this->decimal($this->value($row, ['neutrofil', 'neutrophil']), 'neutrophil'),
                'lymphocyte' => $this->decimal($this->value($row, ['limfosit', 'lymphocyte']), 'lymphocyte'),
                'monocyte' => $this->decimal($this->value($row, ['monosit', 'monocyte']), 'monocyte'),
                'gds' => $this->decimal($this->value($row, ['gds']), 'gds'),
                'fasting_glucose' => $this->decimal($this->value($row, ['glukosa darah puasa', 'gdp', 'fbs']), 'fasting_glucose'),
                'glucose_2h_pp' => $this->decimal($this->value($row, ['glukosa darah 2 jam pp', 'gula darah 2 jam pp', '2 jam pp']), 'glucose_2h_pp'),
                'urea' => $this->decimal($this->value($row, ['ureum', 'urea']), 'urea'),
                'creatinine' => $this->decimal($this->value($row, ['creatinin', 'creatinine']), 'creatinine'),
                'cholesterol' => $this->decimal($this->value($row, ['chol']), 'cholesterol'),
                'uric_acid' => $this->decimal($this->value($row, ['ua']), 'uric_acid'),
                'sgot' => $this->decimal($this->value($row, ['sgot']), 'sgot'),
                'sgpt' => $this->decimal($this->value($row, ['sgpt']), 'sgpt'),
            ],
            'urine' => [
                'color' => $this->text($this->value($row, ['warna urine', 'warna']), 'urine.color', 50),
                'clarity' => $this->text($this->value($row, ['kejernihan', 'jernih']), 'urine.clarity', 50),
                'specific_gravity' => $this->decimal($this->value($row, ['bj', 'berat jenis']), 'urine.specific_gravity'),
                'ph' => $this->decimal($this->value($row, ['ph']), 'urine.ph'),
                'protein' => $this->text($this->value($row, ['protein']), 'urine.protein', 100),
                'glucose_reduction' => $this->text($this->value($row, ['glukosa/reduksi', 'reduksi', 'glukosa']), 'urine.glucose_reduction', 100),
                'urobilinogen' => $this->text($this->value($row, ['urobilinogen']), 'urine.urobilinogen', 100),
                'bilirubin' => $this->text($this->value($row, ['bilirubin']), 'urine.bilirubin', 100),
                'occult_blood' => $this->text($this->value($row, ['blood', 'darah samar']), 'urine.occult_blood', 100),
                'ketone' => $this->text($this->value($row, ['keton', 'ketone']), 'urine.ketone', 100),
                'nitrite' => $this->text($this->value($row, ['nitrit', 'nitrite']), 'urine.nitrite', 100),
                'leukocyte_esterase' => $this->text($this->value($row, ['leukosit esterase', 'lekosit']), 'urine.leukocyte_esterase', 100),
                'erythrocytes' => $this->text($this->value($row, ['eritrosit']), 'urine.erythrocytes', 100),
                'leukocytes' => $this->text($this->value($row, ['lekosit2', 'leukosit']), 'urine.leukocytes', 100),
                'squamous_cells' => $this->text($this->value($row, ['squamos', 'squamous', 'epitel', 'sel epitel']), 'urine.squamous_cells', 100),
                'casts' => $this->text($this->value($row, ['silinder']), 'urine.casts', 100),
                'crystals' => $this->text($this->value($row, ['kristal']), 'urine.crystals', 100),
                'bacteria' => $this->text($this->value($row, ['bakteri']), 'urine.bacteria', 100),
            ],
            'widal' => [
                'widal_o' => $this->text($this->value($row, ['widal o', 'o']), 'widal.o', 50),
                'widal_ao' => $this->text($this->value($row, ['widal ao', 'ao']), 'widal.ao', 50),
                'widal_bo' => $this->text($this->value($row, ['widal bo', 'bo']), 'widal.bo', 50),
                'widal_h' => $this->text($this->value($row, ['widal h', 'h']), 'widal.h', 50),
            ],
        ];
    }

    /** @param array<string, string|null> $row @return array<string, ?string> */
    private function mapDrug(array $row): array
    {
        return [
            'amphetamine' => $this->text($this->value($row, ['amphetamine']), 'drug.amphetamine', 30),
            'marijuana' => $this->text($this->value($row, ['marijuana']), 'drug.marijuana', 30),
            'morphine' => $this->text($this->value($row, ['morphine']), 'drug.morphine', 30),
            'benzodiazepine' => $this->text($this->value($row, ['benzodiazepine']), 'drug.benzodiazepine', 30),
            'methamphetamine' => $this->text($this->value($row, ['metamphetamine', 'methamphetamine']), 'drug.methamphetamine', 30),
            'cocaine' => $this->text($this->value($row, ['cocaine']), 'drug.cocaine', 30),
        ];
    }

    /** @param array<string, string|null> $row @return array<string, string|null> */
    private function normalizeRowKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $key = strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $key)));
            $normalized[$key] = $value === null ? null : trim((string) $value);
        }
        return $normalized;
    }

    /** @param array<string, string|null> $row @param list<string> $aliases */
    private function value(array $row, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $row)) {
                return $row[$alias];
            }
        }
        return null;
    }

    private function requiredText(?string $value, string $field, int $maxLength): ?string
    {
        $value = $this->text($value, $field, $maxLength);
        if ($value === null) {
            $this->error($field, 'required', 'Kolom wajib tidak terisi.');
        }
        return $value;
    }

    private function text(?string $value, string $field, int $maxLength): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }
        if (mb_strlen($value) > $maxLength) {
            $this->error($field, 'too_long', "Nilai melebihi batas {$maxLength} karakter.");
            return null;
        }
        return $value;
    }

    private function decimal(?string $value, string $field): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $this->error($field, 'ambiguous_number', 'Format angka memiliki pemisah yang ambigu.');
            return null;
        }
        $normalized = str_replace(',', '.', $value);
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            $this->error($field, 'invalid_number', 'Nilai bukan angka yang valid.');
            return null;
        }
        return $normalized;
    }

    private function date(?string $value, string $field, bool $required = false): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            if ($required) {
                $this->error($field, 'required', 'Tanggal wajib tidak terisi.');
            }
            return null;
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $value) && (float) $value > 1) {
            $days = (int) floor((float) $value);
            return (new DateTimeImmutable('1899-12-30'))->modify("+{$days} days")->format('Y-m-d');
        }

        $indonesianMonths = ['januari' => 1, 'februari' => 2, 'febuari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12];
        if (preg_match('/^(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})$/u', strtolower($value), $matches) && isset($indonesianMonths[$matches[2]])) {
            return (new DateTimeImmutable(sprintf('%04d-%02d-%02d', (int) $matches[3], $indonesianMonths[$matches[2]], (int) $matches[1])))->format('Y-m-d');
        }

        foreach (['!Y-m-d', '!d/m/Y', '!j/n/Y', '!d-m-Y', '!j-n-Y', '!d.m.Y', '!j.n.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date && $date->format(substr($format, 1)) === $value) {
                return $date->format('Y-m-d');
            }
        }

        $this->error($field, 'invalid_date', 'Format tanggal tidak dikenali.');
        return null;
    }

    private function time(?string $value): ?string
    {
        $value = preg_replace('/\s+/', '', trim((string) $value)) ?? '';
        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',') && !str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        }

        if (preg_match('/^0(?:\.\d+)?$/', $value)) {
            $seconds = (int) round((float) $value * 86400) % 86400;
            return gmdate('H:i:s', $seconds);
        }

        foreach (['!H:i:s', '!H:i'] as $format) {
            $time = DateTimeImmutable::createFromFormat($format, $value);
            if ($time && $time->format(substr($format, 1)) === $value) {
                return $time->format('H:i:s');
            }
        }

        $this->error('examined_time', 'invalid_time', 'Format jam periksa tidak dikenali.');
        return null;
    }

    /** @return array{place:?string,date:?string} */
    private function birthDetails(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return ['place' => null, 'date' => null];
        }

        $parts = preg_split('/,\s*/u', $value) ?: [];
        if (count($parts) >= 2) {
            $dateText = array_pop($parts);
            $place = trim(implode(', ', $parts));
            $errorCount = count($this->errors);
            $date = $this->date($dateText, 'birth_date');
            if ($date !== null) {
                return ['place' => $place, 'date' => $date];
            }
            $this->errors = array_slice($this->errors, 0, $errorCount);
        }

        $this->warning('birth_date', 'unparsed_ttl', 'TTL disimpan sebagai tempat lahir; tanggal lahir perlu ditinjau.');
        return ['place' => $value, 'date' => null];
    }

    private function sex(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return null;
        }
        if (in_array($normalized, ['l', 'laki-laki', 'laki laki', 'male'], true)) {
            return 'L';
        }
        if (in_array($normalized, ['p', 'perempuan', 'female'], true)) {
            return 'P';
        }
        $this->error('sex', 'invalid_choice', 'Jenis kelamin tidak dikenali.');
        return null;
    }

    private function bloodGroup(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (!in_array($value, ['A', 'B', 'AB', 'O'], true)) {
            $this->error('blood_group', 'invalid_choice', 'Golongan darah tidak dikenali.');
            return null;
        }
        return $value;
    }

    private function rhesus(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (in_array($value, ['+', 'positif', 'positive', 'pos'], true)) {
            return '+';
        }
        if (in_array($value, ['-', 'negatif', 'negative', 'neg'], true)) {
            return '-';
        }
        $this->error('rhesus', 'invalid_choice', 'Rhesus tidak dikenali.');
        return null;
    }

    /** @return array{0:?string,1:?string} */
    private function bloodPressure(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return [null, null];
        }
        if (!preg_match('/^(\d{2,3})\s*[\/-]\s*(\d{2,3})$/', $value, $matches)) {
            $this->error('blood_pressure', 'invalid_format', 'Tekanan darah harus berformat sistolik/diastolik.');
            return [null, null];
        }
        return [$matches[1], $matches[2]];
    }

    private function error(string $field, string $code, string $message): void
    {
        $this->errors[] = compact('field', 'code', 'message');
    }

    private function warning(string $field, string $code, string $message): void
    {
        $this->warnings[] = compact('field', 'code', 'message');
    }
}
