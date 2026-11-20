<?php

declare(strict_types=1);

final class PatientRepository
{
    public const INSURANCE_TYPES = [
        'Umum' => 'Umum (non-BPJS)',
        'BPJS Kesehatan' => 'BPJS Kesehatan',
        'Pemerintah Daerah Kota' => 'Pemerintah Daerah Kota',
    ];

    /**
     * Returns a suggested next medical-record number without reserving it.
     *
     * Existing non-numeric record numbers are intentionally ignored: staff may
     * still use a clinic-specific manual format when needed. The suggestion is
     * based on the highest numeric value and retains the zero padding of that
     * value (for example, 0100 becomes 0101).
     */
    public static function nextMedicalRecordNumber(?PDO $pdo = null): string
    {
        $pdo ??= Database::connection();
        $statement = $pdo->query(
            "SELECT medical_record_number
             FROM patients
             WHERE medical_record_number REGEXP '^[0-9]+$'
             ORDER BY
                CHAR_LENGTH(COALESCE(NULLIF(TRIM(LEADING '0' FROM medical_record_number), ''), '0')) DESC,
                COALESCE(NULLIF(TRIM(LEADING '0' FROM medical_record_number), ''), '0') DESC,
                CHAR_LENGTH(medical_record_number) DESC
             LIMIT 1"
        );
        $lastNumber = $statement === false ? false : $statement->fetchColumn();

        if ($lastNumber === false) {
            return '0001';
        }

        $nextNumber = self::incrementNumericString((string) $lastNumber);
        if (strlen($nextNumber) > 50) {
            throw new RuntimeException('Nomor rekam medis otomatis melebihi batas panjang yang didukung.');
        }

        return $nextNumber;
    }

    /** @return list<array<string, mixed>> */
    public static function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT id, medical_record_number, national_id, full_name, birth_date, sex, insurance_type
             FROM patients
             WHERE archived_at IS NULL
               AND (medical_record_number LIKE :medical_record_query
                OR national_id LIKE :national_id_query
                OR full_name LIKE :full_name_query)
             ORDER BY full_name ASC
             LIMIT 50'
        );
        $like = '%' . $query . '%';
        $statement->execute([
            'medical_record_query' => $like,
            'national_id_query' => $like,
            'full_name_query' => $like,
        ]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function suggestions(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $statement = Database::connection()->prepare(
            'SELECT id, medical_record_number, national_id, full_name, birth_date, sex, insurance_type
             FROM patients
             WHERE archived_at IS NULL
               AND (medical_record_number LIKE :medical_record_query
                OR national_id LIKE :national_id_query
                OR full_name LIKE :full_name_query)
             ORDER BY
                CASE WHEN full_name LIKE :full_name_prefix THEN 0 ELSE 1 END,
                full_name ASC
             LIMIT 8'
        );
        $like = '%' . $query . '%';
        $statement->execute([
            'medical_record_query' => $like,
            'national_id_query' => $like,
            'full_name_query' => $like,
            'full_name_prefix' => $query . '%',
        ]);

        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function archived(string $query = ''): array
    {
        $query = trim($query);
        $sql = 'SELECT id, medical_record_number, national_id, full_name, birth_date, sex, insurance_type, archived_at
                FROM patients
                WHERE archived_at IS NOT NULL';
        $parameters = [];
        if ($query !== '') {
            $sql .= ' AND (medical_record_number LIKE :medical_record_query
                       OR national_id LIKE :national_id_query
                       OR full_name LIKE :full_name_query)';
            $like = '%' . $query . '%';
            $parameters = [
                'medical_record_query' => $like,
                'national_id_query' => $like,
                'full_name_query' => $like,
            ];
        }
        $sql .= ' ORDER BY archived_at DESC, full_name ASC LIMIT 100';

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|false */
    public static function find(int $id): array|false
    {
        $statement = Database::connection()->prepare('SELECT * FROM patients WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        return $statement->fetch();
    }

    /** @return list<array<string, mixed>> */
    public static function examinations(int $patientId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT e.id, e.lab_number, e.examination_type, e.status, e.examined_on, e.examined_time,
                    e.room, e.condition_note, e.purpose,
                    b.hgb, b.wbc, b.plt, b.rbc, b.hct, b.lymph_pct, b.mid_pct, b.gran_pct, b.mcv, b.mch, b.mchc,
                    b.eosinophil, b.basophil, b.neutrophil, b.lymphocyte, b.monocyte, b.gds, b.fasting_glucose, b.glucose_2h_pp,
                    b.urea, b.creatinine, b.cholesterol, b.uric_acid, b.sgot, b.sgpt,
                    u.color AS urine_color, u.clarity AS urine_clarity, u.specific_gravity AS urine_specific_gravity, u.ph AS urine_ph,
                    u.protein, u.glucose_reduction, u.urobilinogen, u.bilirubin, u.occult_blood, u.ketone, u.nitrite,
                    u.leukocyte_esterase, u.erythrocytes, u.leukocytes, u.squamous_cells, u.casts, u.crystals, u.bacteria,
                    d.amphetamine AS drug_amphetamine, d.marijuana AS drug_marijuana, d.morphine AS drug_morphine,
                    d.benzodiazepine AS drug_benzodiazepine, d.methamphetamine AS drug_methamphetamine, d.cocaine AS drug_cocaine,
                    w.widal_o, w.widal_ao, w.widal_bo, w.widal_h
             FROM examinations e
             LEFT JOIN blood_results b ON b.examination_id = e.id
             LEFT JOIN urine_results u ON u.examination_id = e.id
             LEFT JOIN drug_results d ON d.examination_id = e.id
             LEFT JOIN widal_results w ON w.examination_id = e.id
             WHERE e.patient_id = :patient_id
               AND e.examined_on >= :retention_start
             ORDER BY e.examined_on DESC, e.id DESC'
        );
        $statement->execute([
            'patient_id' => $patientId,
            'retention_start' => ExaminationRepository::DATA_RETENTION_START,
        ]);
        $examinations = $statement->fetchAll();
        if ($examinations === []) {
            return [];
        }

        $examinationIds = array_map(static fn (array $examination): int => (int) $examination['id'], $examinations);
        $placeholders = [];
        $parameters = [];
        foreach ($examinationIds as $index => $examinationId) {
            $name = 'examination_id_' . $index;
            $placeholders[] = ':' . $name;
            $parameters[$name] = $examinationId;
        }

        $resultStatement = Database::connection()->prepare(
            'SELECT r.examination_id, r.parameter_code_snapshot, r.parameter_label_snapshot, r.value_numeric, r.value_text
             FROM examination_parameter_results r
             LEFT JOIN test_parameters tp ON tp.id = r.parameter_id
             LEFT JOIN examination_panels ep ON ep.id = tp.panel_id
             WHERE r.examination_id IN (' . implode(', ', $placeholders) . ')
               AND (r.value_numeric IS NOT NULL OR NULLIF(TRIM(r.value_text), "") IS NOT NULL)
             ORDER BY r.examination_id, COALESCE(ep.display_order, 9999), COALESCE(tp.display_order, 9999), r.id'
        );
        $resultStatement->execute($parameters);

        /** @var array<int, list<array{label:string, value:string, numeric:bool}>> $summaries */
        $summaries = [];
        /** @var array<int, array<string, true>> $includedCodes */
        $includedCodes = [];
        foreach ($resultStatement->fetchAll() as $result) {
            $examinationId = (int) $result['examination_id'];
            $numeric = $result['value_numeric'] !== null;
            $value = trim((string) ($numeric ? $result['value_numeric'] : $result['value_text']));
            if ($value === '') {
                continue;
            }

            $summaries[$examinationId][] = [
                'label' => (string) $result['parameter_label_snapshot'],
                'value' => $value,
                'numeric' => $numeric,
            ];
            $includedCodes[$examinationId][(string) $result['parameter_code_snapshot']] = true;
        }

        foreach ($examinations as &$examination) {
            $examinationId = (int) $examination['id'];
            foreach (LabTestCatalog::all() as $code => $test) {
                $field = $test['field'];
                $value = $examination[$field] ?? null;
                if (isset($includedCodes[$examinationId][$code]) || $value === null || trim((string) $value) === '') {
                    continue;
                }

                $summaries[$examinationId][] = [
                    'label' => $test['label'],
                    'value' => trim((string) $value),
                    'numeric' => $test['kind'] === 'numeric',
                ];
            }
            $examination['result_summary'] = $summaries[$examinationId] ?? [];
        }
        unset($examination);

        return $examinations;
    }

    /** @param array<string, mixed> $input */
    public static function create(array $input, int $userId): int
    {
        $data = self::validate($input, true);
        $statement = Database::connection()->prepare(
            'INSERT INTO patients
            (medical_record_number, national_id, full_name, birth_place, birth_date, sex, address, phone, occupation, insurance_type, insurance_number, created_at, updated_at)
             VALUES
                (:medical_record_number, :national_id, :full_name, :birth_place, :birth_date, :sex, :address, :phone, :occupation, :insurance_type, :insurance_number, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        try {
            $statement->execute($data);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new ValidationException(['medical_record_number' => 'Nomor rekam medis atau NIK sudah terdaftar.']);
            }
            throw $exception;
        }

        $id = (int) Database::connection()->lastInsertId();
        AuditLogger::record('patient.created', $userId, 'patient', $id, null, ['medical_record_number' => $data['medical_record_number']]);

        return $id;
    }

    /** @param array<string, mixed> $input */
    public static function update(int $id, array $input, int $userId): void
    {
        $before = self::find($id);
        if ($before === false) {
            throw new ValidationException(['form' => 'Pasien tidak ditemukan.']);
        }
        if ($before['archived_at'] !== null) {
            throw new ValidationException(['form' => 'Pasien telah diarsipkan. Pulihkan data pasien terlebih dahulu sebelum mengubah identitasnya.']);
        }

        $data = self::validate($input);
        $statement = Database::connection()->prepare(
            'UPDATE patients SET
                medical_record_number = :medical_record_number,
                national_id = :national_id,
                full_name = :full_name,
                birth_place = :birth_place,
                birth_date = :birth_date,
                sex = :sex,
                address = :address,
                phone = :phone,
                occupation = :occupation,
                insurance_type = :insurance_type,
                insurance_number = :insurance_number,
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id'
        );
        try {
            $statement->execute($data + ['id' => $id]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new ValidationException(['form' => 'Nomor rekam medis atau NIK sudah digunakan pasien lain.']);
            }
            throw $exception;
        }

        $after = self::find($id);
        AuditLogger::record('patient.updated', $userId, 'patient', $id, $before, $after === false ? $data : $after);
    }

    /** @deprecated Kept only for compatibility with old records; new UI uses permanent deletion. */
    public static function archive(int $id, string $confirmationMedicalRecordNumber, int $userId): void
    {
        $before = self::requireConfirmedPatient($id, $confirmationMedicalRecordNumber);
        if ($before['archived_at'] !== null) {
            throw new ValidationException(['form' => 'Pasien sudah diarsipkan.']);
        }

        Database::connection()->prepare(
            'UPDATE patients
             SET archived_at = UTC_TIMESTAMP(), archived_by = :archived_by, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND archived_at IS NULL'
        )->execute(['archived_by' => $userId, 'id' => $id]);

        $after = self::find($id);
        AuditLogger::record('patient.archived', $userId, 'patient', $id, $before, $after === false ? $before : $after);
    }

    /** @deprecated Kept only for compatibility with old records; new UI no longer offers restore. */
    public static function restore(int $id, int $userId): void
    {
        $before = self::find($id);
        if ($before === false) {
            throw new ValidationException(['form' => 'Pasien tidak ditemukan.']);
        }
        if ($before['archived_at'] === null) {
            throw new ValidationException(['form' => 'Pasien belum diarsipkan.']);
        }

        Database::connection()->prepare(
            'UPDATE patients
             SET archived_at = NULL, archived_by = NULL, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND archived_at IS NOT NULL'
        )->execute(['id' => $id]);

        $after = self::find($id);
        AuditLogger::record('patient.restored', $userId, 'patient', $id, $before, $after === false ? $before : $after);
    }

    public static function deletePermanently(int $id, string $confirmationMedicalRecordNumber, int $userId): void
    {
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $statement = $pdo->prepare('SELECT * FROM patients WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $id]);
            $before = $statement->fetch();
            if ($before === false) {
                throw new ValidationException(['form' => 'Pasien tidak ditemukan.']);
            }
            self::assertMedicalRecordConfirmation($before, $confirmationMedicalRecordNumber);

            $dateStatement = $pdo->prepare(
                'SELECT DISTINCT examined_on FROM examinations WHERE patient_id = :patient_id'
            );
            $dateStatement->execute(['patient_id' => $id]);
            $examinedDates = array_values(array_filter(
                array_map(static fn (mixed $date): string => (string) $date, $dateStatement->fetchAll(PDO::FETCH_COLUMN)),
                static fn (string $date): bool => $date !== ''
            ));

            // Some legacy foreign keys intentionally use RESTRICT (print logs and
            // examination ownership). Remove examination dependants explicitly so
            // a patient and the complete clinical record are deleted atomically.
            $dependentTables = [
                'vital_results',
                'blood_results',
                'urine_results',
                'drug_results',
                'widal_results',
                'examination_panel_selections',
                'examination_parameter_results',
                'examination_result_flags',
                'print_logs',
            ];
            foreach ($dependentTables as $table) {
                $pdo->prepare(
                    'DELETE FROM ' . $table . '
                     WHERE examination_id IN (
                         SELECT id FROM examinations WHERE patient_id = :patient_id
                     )'
                )->execute(['patient_id' => $id]);
            }

            $pdo->prepare('DELETE FROM examinations WHERE patient_id = :patient_id')
                ->execute(['patient_id' => $id]);
            $pdo->prepare('DELETE FROM patients WHERE id = :id')->execute(['id' => $id]);

            // Sequence rows are derived state. Recreate them from the remaining
            // examinations so deleting the last test for a date starts at 0001.
            if ($examinedDates !== []) {
                $placeholders = implode(', ', array_fill(0, count($examinedDates), '?'));
                $pdo->prepare('DELETE FROM lab_number_sequences WHERE examined_on IN (' . $placeholders . ')')
                    ->execute($examinedDates);
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        AuditLogger::record('patient.deleted_permanently', $userId, 'patient', $id, $before, null);
    }

    /** @param array<string, mixed> $input @return array<string, string|null> */
    private static function validate(array $input, bool $requireInsurance = false): array
    {
        $data = [
            'medical_record_number' => self::text($input['medical_record_number'] ?? null, 'Nomor rekam medis', 'medical_record_number', 50, true),
            'national_id' => self::text($input['national_id'] ?? null, 'NIK', 'national_id', 32),
            'full_name' => self::text($input['full_name'] ?? null, 'Nama pasien', 'full_name', 150, true),
            'birth_place' => self::text($input['birth_place'] ?? null, 'Tempat lahir', 'birth_place', 100),
            'birth_date' => self::date($input['birth_date'] ?? null),
            'sex' => self::sex($input['sex'] ?? null),
            'address' => self::text($input['address'] ?? null, 'Alamat', 'address', 1000),
            'phone' => self::text($input['phone'] ?? null, 'Nomor telepon', 'phone', 30),
            'occupation' => self::text($input['occupation'] ?? null, 'Pekerjaan', 'occupation', 150),
            'insurance_type' => self::insuranceType($input['insurance_type'] ?? null, $requireInsurance),
            'insurance_number' => self::text($input['insurance_number'] ?? null, 'No. Asuransi', 'insurance_number', 50),
        ];

        return $data;
    }

    private static function text(mixed $value, string $label, string $errorKey, int $maxLength, bool $required = false): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            if ($required) {
                throw new ValidationException([$errorKey => $label . ' wajib diisi.']);
            }
            return null;
        }
        if (mb_strlen($value) > $maxLength) {
            throw new ValidationException([$errorKey => $label . ' terlalu panjang.']);
        }

        return $value;
    }

    private static function date(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})$/', $value, $matches) === 1) {
            $value = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new ValidationException(['birth_date' => 'Tanggal lahir tidak valid.']);
        }

        return $value;
    }

    private static function sex(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!in_array($value, ['L', 'P'], true)) {
            throw new ValidationException(['sex' => 'Jenis kelamin tidak valid.']);
        }

        return $value;
    }

    private static function insuranceType(mixed $value, bool $required = false): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            if ($required) {
                throw new ValidationException(['insurance_type' => 'Asuransi/penjamin wajib dipilih.']);
            }
            return null;
        }
        if (!array_key_exists($value, self::INSURANCE_TYPES)) {
            throw new ValidationException(['insurance_type' => 'Jenis penjamin/asuransi tidak valid.']);
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function requireConfirmedPatient(int $id, string $confirmationMedicalRecordNumber): array
    {
        $patient = self::find($id);
        if ($patient === false) {
            throw new ValidationException(['form' => 'Pasien tidak ditemukan.']);
        }

        self::assertMedicalRecordConfirmation($patient, $confirmationMedicalRecordNumber);

        return $patient;
    }

    /** @param array<string, mixed> $patient */
    private static function assertMedicalRecordConfirmation(array $patient, string $confirmationMedicalRecordNumber): void
    {
        if (!hash_equals((string) $patient['medical_record_number'], trim($confirmationMedicalRecordNumber))) {
            throw new ValidationException(['form' => 'Nomor RM konfirmasi tidak sesuai. Tindakan dibatalkan.']);
        }
    }

    private static function incrementNumericString(string $number): string
    {
        $digits = str_split($number);
        $carry = 1;

        for ($index = count($digits) - 1; $index >= 0; $index--) {
            $digit = (int) $digits[$index] + $carry;
            $digits[$index] = (string) ($digit % 10);
            $carry = intdiv($digit, 10);

            if ($carry === 0) {
                break;
            }
        }

        return $carry === 1 ? '1' . implode('', $digits) : implode('', $digits);
    }
}
