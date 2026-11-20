<?php

declare(strict_types=1);

final class ExaminationRepository
{
    public const DATA_RETENTION_START = '2026-01-01';

    public static function nowInWib(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    }

    public static function suggestLabNumber(string $examinedOn): string
    {
        self::assertRetainedExamDate($examinedOn);

        return self::formatLabNumber($examinedOn, self::nextSequenceFromExisting(Database::connection(), $examinedOn));
    }
    /** @return array<string, mixed>|false */
    public static function find(int $id): array|false
    {
        $statement = Database::connection()->prepare(
            'SELECT e.*, p.medical_record_number, p.national_id, p.full_name, p.birth_place, p.birth_date, p.sex, p.address, p.phone, p.insurance_type,
                    v.blood_pressure_systolic, v.blood_pressure_diastolic, v.height_cm, v.weight_kg, v.respiratory_rate, v.color_vision,
                    v.pulse_rate, v.blood_group, v.rhesus, v.temperature_c,
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
             INNER JOIN patients p ON p.id = e.patient_id
             LEFT JOIN vital_results v ON v.examination_id = e.id
             LEFT JOIN blood_results b ON b.examination_id = e.id
             LEFT JOIN urine_results u ON u.examination_id = e.id
             LEFT JOIN drug_results d ON d.examination_id = e.id
             LEFT JOIN widal_results w ON w.examination_id = e.id
             WHERE e.id = :id
               AND e.examined_on >= :retention_start
             LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
            'retention_start' => self::DATA_RETENTION_START,
        ]);

        return $statement->fetch();
    }

    /** @param array<string, mixed> $input */
    public static function create(array $input, int $userId): int
    {
        $patientId = filter_var($input['patient_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $patient = $patientId === false ? false : PatientRepository::find((int) $patientId);
        if ($patient === false) {
            throw new ValidationException(['patient_id' => 'Pasien tidak ditemukan.']);
        }
        if ($patient['archived_at'] !== null) {
            throw new ValidationException(['patient_id' => 'Pasien telah diarsipkan dan tidak dapat dibuatkan pemeriksaan baru.']);
        }
        $labNumber = trim((string) ($input['lab_number'] ?? ''));
        $suggestedLabNumber = trim((string) ($input['suggested_lab_number'] ?? ''));
        $useAutomaticLabNumber = $suggestedLabNumber !== '' && hash_equals($suggestedLabNumber, $labNumber);
        $examinedOn = trim((string) ($input['examined_on'] ?? ''));
        $examinationType = trim((string) ($input['examination_type'] ?? 'mcu_general'));
        if ($patientId === false || $labNumber === '' || $examinedOn === '') {
            throw new ValidationException(['form' => 'Pasien, nomor lab, dan tanggal periksa wajib diisi.']);
        }
        if (mb_strlen($labNumber) > 50) {
            throw new ValidationException(['lab_number' => 'Nomor lab terlalu panjang.']);
        }
        self::assertRetainedExamDate($examinedOn);
        if (!ExaminationType::isValid($examinationType)) {
            throw new ValidationException(['examination_type' => 'Jenis pemeriksaan tidak valid.']);
        }
        $pdo = Database::connection();
        $examinedTime = self::time($input['examined_time'] ?? null);
        $room = self::requiredText($input['room'] ?? null, 'Ruangan', 100);
        $panelSelections = $examinationType === 'mcu_general'
            ? ExaminationPanelCatalogRepository::selectionFromInput($input['panel_codes'] ?? [], $pdo, $patient['sex'] ?? null)
            : [];

        try {
            $pdo->beginTransaction();
            if ($useAutomaticLabNumber) {
                $labNumber = self::formatLabNumber($examinedOn, self::reserveNextSequence($pdo, $examinedOn));
            }
            $statement = $pdo->prepare(
                'INSERT INTO examinations
                    (patient_id, lab_number, examination_type, status, examined_on, result_date, examined_time, condition_note, purpose, letter_number, room, created_by, updated_by, created_at, updated_at)
                 VALUES
                    (:patient_id, :lab_number, :examination_type, "draft", :examined_on, :result_date, :examined_time, :condition_note, :purpose, :letter_number, :room, :created_by, :updated_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $statement->execute([
                'patient_id' => (int) $patientId,
                'lab_number' => $labNumber,
                'examination_type' => $examinationType,
                'examined_on' => $examinedOn,
                'result_date' => $examinedOn,
                'examined_time' => $examinedTime,
                'condition_note' => self::nullableText($input['condition_note'] ?? null, 500),
                'purpose' => self::nullableText($input['purpose'] ?? null, 255),
                'letter_number' => self::nullableText($input['letter_number'] ?? null, 100),
                'room' => $room,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $id = (int) $pdo->lastInsertId();
            ExaminationPanelCatalogRepository::storeSelections($pdo, $id, $panelSelections);
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() === '23000') {
                throw new ValidationException(['lab_number' => 'Nomor lab sudah digunakan.']);
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        AuditLogger::record('examination.created', $userId, 'examination', $id, null, [
            'lab_number' => $labNumber,
            'examined_time' => $examinedTime,
            'type' => $examinationType,
            'panel_codes' => array_column($panelSelections, 'code'),
        ]);

        return $id;
    }

    public static function updateDocumentDates(int $id, string $examinedOn, string $resultDate, int $userId): void
    {
        $examinedOn = trim($examinedOn);
        $resultDate = trim($resultDate);
        self::assertRetainedExamDate($examinedOn);
        if (!self::validDate($resultDate)) {
            throw new ValidationException(['result_date' => 'Tanggal hasil tidak valid.']);
        }
        if ($resultDate < $examinedOn) {
            throw new ValidationException(['result_date' => 'Tanggal hasil tidak boleh lebih awal dari tanggal periksa.']);
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'SELECT *
                 FROM examinations
                 WHERE id = :id
                   AND examined_on >= :retention_start
                 FOR UPDATE'
            );
            $statement->execute(['id' => $id, 'retention_start' => self::DATA_RETENTION_START]);
            $before = $statement->fetch();
            if ($before === false) {
                throw new ValidationException(['form' => 'Pemeriksaan tidak ditemukan.']);
            }

            $pdo->prepare(
                'UPDATE examinations
                 SET examined_on = :examined_on, result_date = :result_date,
                     updated_by = :updated_by, updated_at = UTC_TIMESTAMP()
                 WHERE id = :id'
            )->execute([
                'examined_on' => $examinedOn,
                'result_date' => $resultDate,
                'updated_by' => $userId,
                'id' => $id,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        AuditLogger::record('examination.document_dates_updated', $userId, 'examination', $id, [
            'examined_on' => $before['examined_on'],
            'result_date' => $before['result_date'] ?? null,
        ], [
            'examined_on' => $examinedOn,
            'result_date' => $resultDate,
        ]);
    }

    public static function deleteExamination(int $id, string $confirmationLabNumber, int $userId): int
    {
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $statement = $pdo->prepare(
                'SELECT *
                 FROM examinations
                 WHERE id = :id
                   AND examined_on >= :retention_start
                 FOR UPDATE'
            );
            $statement->execute([
                'id' => $id,
                'retention_start' => self::DATA_RETENTION_START,
            ]);
            $before = $statement->fetch();
            if ($before === false) {
                throw new ValidationException(['form' => 'Pemeriksaan tidak ditemukan.']);
            }

            $confirmationLabNumber = trim($confirmationLabNumber);
            if ($confirmationLabNumber === '' || !hash_equals((string) $before['lab_number'], $confirmationLabNumber)) {
                throw new ValidationException(['confirm_lab_number' => 'Konfirmasi nomor lab tidak sesuai.']);
            }

            foreach ([
                'vital_results',
                'blood_results',
                'urine_results',
                'drug_results',
                'widal_results',
                'examination_panel_selections',
                'examination_parameter_results',
                'examination_result_flags',
                'print_logs',
            ] as $table) {
                $pdo->prepare('DELETE FROM ' . $table . ' WHERE examination_id = :examination_id')
                    ->execute(['examination_id' => $id]);
            }

            $pdo->prepare('DELETE FROM examinations WHERE id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM lab_number_sequences WHERE examined_on = :examined_on')
                ->execute(['examined_on' => $before['examined_on']]);

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        AuditLogger::record('examination.deleted_permanently', $userId, 'examination', $id, $before, null);

        return (int) $before['patient_id'];
    }

    private static function reserveNextSequence(PDO $pdo, string $examinedOn): int
    {
        $initialSequence = self::nextSequenceFromExisting($pdo, $examinedOn);
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO lab_number_sequences (examined_on, next_sequence, created_at, updated_at)
             VALUES (:examined_on, :next_sequence, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $insert->execute(['examined_on' => $examinedOn, 'next_sequence' => $initialSequence]);

        $select = $pdo->prepare('SELECT next_sequence FROM lab_number_sequences WHERE examined_on = :examined_on FOR UPDATE');
        $select->execute(['examined_on' => $examinedOn]);
        $row = $select->fetch();
        if ($row === false) {
            throw new RuntimeException('Nomor urut laboratorium tidak dapat disiapkan.');
        }

        $sequence = max((int) $row['next_sequence'], self::nextSequenceFromExisting($pdo, $examinedOn));
        self::formatLabNumber($examinedOn, $sequence);
        $update = $pdo->prepare(
            'UPDATE lab_number_sequences
             SET next_sequence = :next_sequence, updated_at = UTC_TIMESTAMP()
             WHERE examined_on = :examined_on'
        );
        $update->execute(['examined_on' => $examinedOn, 'next_sequence' => $sequence + 1]);

        return $sequence;
    }

    private static function nextSequenceFromExisting(PDO $pdo, string $examinedOn): int
    {
        $prefix = self::labDatePrefix($examinedOn);
        $statement = $pdo->prepare('SELECT lab_number FROM examinations WHERE lab_number LIKE :prefix');
        $statement->execute(['prefix' => $prefix . '%']);

        $highestSequence = 0;
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $labNumber) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\\d{4})$/', (string) $labNumber, $matches) === 1) {
                $highestSequence = max($highestSequence, (int) $matches[1]);
            }
        }

        return $highestSequence + 1;
    }

    private static function formatLabNumber(string $examinedOn, int $sequence): string
    {
        if ($sequence < 1 || $sequence > 9999) {
            throw new ValidationException(['lab_number' => 'Nomor urut laboratorium untuk tanggal ini sudah mencapai batas 9999.']);
        }

        return self::labDatePrefix($examinedOn) . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private static function labDatePrefix(string $examinedOn): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $examinedOn);
        if ($date === false || $date->format('Y-m-d') !== $examinedOn) {
            throw new ValidationException(['examined_on' => 'Tanggal periksa tidak valid.']);
        }

        return $date->format('ymd');
    }

    private static function assertRetainedExamDate(string $examinedOn): void
    {
        if (!self::validDate($examinedOn)) {
            throw new ValidationException(['examined_on' => 'Tanggal periksa tidak valid.']);
        }
        if ($examinedOn < self::DATA_RETENTION_START) {
            throw new ValidationException(['examined_on' => 'Tanggal periksa harus 01/01/2026 atau setelahnya.']);
        }
    }

    /** @param array<string, mixed> $input */
    public static function saveResults(int $id, array $input, int $userId): void
    {
        $examination = self::find($id);
        if ($examination === false) {
            throw new RuntimeException('Pemeriksaan tidak ditemukan.');
        }
        $panelForm = $examination['examination_type'] === 'mcu_general'
            ? ExaminationPanelCatalogRepository::formForExamination($id)
            : [];
        if ($panelForm !== []) {
            ExaminationPanelCatalogRepository::assertPatientSex($panelForm, $examination['sex'] ?? null);
            self::savePanelResults($id, $input, $userId, $panelForm);
            return;
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $sections = [];
            if ($examination['examination_type'] === 'mcu_general') {
                self::upsert($pdo, 'vital_results', 'examination_id', $id, self::vitalData($input));
                self::upsert($pdo, 'blood_results', 'examination_id', $id, self::bloodData($input));
                self::upsert($pdo, 'urine_results', 'examination_id', $id, self::urineData($input));
                self::upsert($pdo, 'widal_results', 'examination_id', $id, self::widalData($input));
                $sections = ['vital', 'blood', 'urine', 'widal'];
            } elseif ($examination['examination_type'] === 'newborn_blood') {
                self::upsert($pdo, 'blood_results', 'examination_id', $id, self::bloodData($input));
                $sections = ['blood'];
            } elseif ($examination['examination_type'] === 'drug_screen') {
                self::upsert($pdo, 'drug_results', 'examination_id', $id, self::drugData($input));
                $sections = ['drug'];
            }
            $pdo->prepare('UPDATE examinations SET updated_by = :user_id, updated_at = UTC_TIMESTAMP() WHERE id = :id')
                ->execute(['user_id' => $userId, 'id' => $id]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        AuditLogger::record('examination.results_updated', $userId, 'examination', $id, null, ['sections' => $sections]);
    }

    /** @param array<string,mixed> $input @param list<array<string,mixed>> $panelForm */
    private static function savePanelResults(int $id, array $input, int $userId, array $panelForm): void
    {
        $normalized = ExaminationParameterResultRepository::normalize($panelForm, $input['parameter_values'] ?? []);
        $submittedValues = is_array($input['parameter_values'] ?? null) ? $input['parameter_values'] : [];
        $legacyInput = self::legacyInputFromPanelSubmission($panelForm, $submittedValues);
        $panelCodes = array_map(static fn (array $panel): string => (string) $panel['code'], $panelForm);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            ExaminationParameterResultRepository::replace($pdo, $id, $normalized['rows']);
            if (array_intersect($panelCodes, ['hematology_complete', 'clinical_chemistry']) !== []) {
                self::upsert($pdo, 'blood_results', 'examination_id', $id, self::bloodData($legacyInput));
            }
            if (in_array('urinalysis_complete', $panelCodes, true)) {
                self::upsert($pdo, 'urine_results', 'examination_id', $id, self::urineData($legacyInput));
            }
            if (in_array('widal', $panelCodes, true)) {
                self::upsert($pdo, 'widal_results', 'examination_id', $id, self::widalData($legacyInput));
            }
            if (in_array('immunoserology', $panelCodes, true)) {
                self::upsert($pdo, 'vital_results', 'examination_id', $id, self::vitalData($legacyInput));
            }
            $pdo->prepare('UPDATE examinations SET updated_by = :user_id, updated_at = UTC_TIMESTAMP() WHERE id = :id')
                ->execute(['user_id' => $userId, 'id' => $id]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        AuditLogger::record('examination.panel_results_updated', $userId, 'examination', $id, null, [
            'panel_codes' => $panelCodes,
            'results' => count($normalized['rows']),
        ]);
    }

    /** @param list<array<string,mixed>> $panelForm @param array<string,mixed> $submittedValues @return array<string,mixed> */
    private static function legacyInputFromPanelSubmission(array $panelForm, array $submittedValues): array
    {
        $legacyInput = [];
        foreach ($panelForm as $panel) {
            foreach ($panel['parameters'] as $parameter) {
                if ($parameter['legacy_field'] === null) {
                    continue;
                }

                $rawValue = $submittedValues[(string) $parameter['code']] ?? null;
                // Keep the user's Indonesian decimal separator for the legacy
                // compatibility tables. The panel result has already been
                // normalized separately for examination_parameter_results.
                $legacyInput[(string) $parameter['legacy_field']] = is_array($rawValue) || is_object($rawValue)
                    ? null
                    : $rawValue;
            }
        }

        return $legacyInput;
    }

    public static function complete(int $id, int $userId): void
    {
        $examination = self::find($id);
        if ($examination === false) {
            throw new RuntimeException('Pemeriksaan tidak ditemukan.');
        }
        if (in_array($examination['status'], ['completed', 'finalized'], true)) {
            return;
        }
        if (trim((string) $examination['medical_record_number']) === '' || trim((string) $examination['full_name']) === '') {
            throw new ValidationException(['form' => 'Nomor rekam medis dan nama pasien wajib tersedia sebelum pemeriksaan diselesaikan.']);
        }

        Database::connection()->prepare(
            'UPDATE examinations
             SET status = "completed", completed_at = UTC_TIMESTAMP(), completed_by = :completed_by, updated_by = :updated_by, updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND status = "draft"'
        )->execute(['completed_by' => $userId, 'updated_by' => $userId, 'id' => $id]);
        AuditLogger::record('examination.completed', $userId, 'examination', $id, ['status' => 'draft'], ['status' => 'completed']);
    }

    /** @param array<string, mixed> $data */
    private static function upsert(PDO $pdo, string $table, string $key, int $id, array $data): void
    {
        $columns = array_keys($data);
        $insertColumns = array_merge([$key], $columns, ['created_at', 'updated_at']);
        $placeholders = array_merge([':' . $key], array_map(static fn(string $column): string => ':' . $column, $columns), ['UTC_TIMESTAMP()', 'UTC_TIMESTAMP()']);
        $updates = array_map(static fn(string $column): string => "{$column} = VALUES({$column})", $columns);
        $updates[] = 'updated_at = UTC_TIMESTAMP()';
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $table,
            implode(', ', $insertColumns),
            implode(', ', $placeholders),
            implode(', ', $updates)
        );
        $statement = $pdo->prepare($sql);
        $statement->execute(array_merge([$key => $id], $data));
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private static function vitalData(array $input): array
    {
        return [
            'blood_pressure_systolic' => self::decimal($input['blood_pressure_systolic'] ?? null, 'Tekanan darah sistolik', 350, 0),
            'blood_pressure_diastolic' => self::decimal($input['blood_pressure_diastolic'] ?? null, 'Tekanan darah diastolik', 250, 0),
            'height_cm' => self::decimal($input['height_cm'] ?? null, 'Tinggi badan', 300, 2),
            'weight_kg' => self::decimal($input['weight_kg'] ?? null, 'Berat badan', 700, 2),
            'respiratory_rate' => self::decimal($input['respiratory_rate'] ?? null, 'RR', 200, 0),
            'color_vision' => self::nullableText($input['color_vision'] ?? null, 50),
            'pulse_rate' => self::decimal($input['pulse_rate'] ?? null, 'Nadi', 300, 0),
            'blood_group' => self::choice($input['blood_group'] ?? null, 'Golongan darah', ['A', 'B', 'AB', 'O']),
            'rhesus' => self::choice($input['rhesus'] ?? null, 'Rhesus', ['+', '-']),
            'temperature_c' => self::decimal($input['temperature_c'] ?? null, 'Suhu', 60, 1),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private static function bloodData(array $input): array
    {
        return [
            'hgb' => self::decimal($input['hgb'] ?? null, 'HGB', 100, 2),
            'wbc' => self::decimal($input['wbc'] ?? null, 'WBC', 1000000, 2),
            'plt' => self::decimal($input['plt'] ?? null, 'PLT', 10000000, 2),
            'rbc' => self::decimal($input['rbc'] ?? null, 'RBC', 100, 2),
            'hct' => self::decimal($input['hct'] ?? null, 'HCT', 100, 2),
            'lymph_pct' => self::decimal($input['lymph_pct'] ?? null, 'LYMPH%', 100, 2),
            'mid_pct' => self::decimal($input['mid_pct'] ?? null, 'MID%', 100, 2),
            'gran_pct' => self::decimal($input['gran_pct'] ?? null, 'GRAN%', 100, 2),
            'mcv' => self::decimal($input['mcv'] ?? null, 'MCV', 1000, 2),
            'mch' => self::decimal($input['mch'] ?? null, 'MCH', 1000, 2),
            'mchc' => self::decimal($input['mchc'] ?? null, 'MCHC', 1000, 2),
            'eosinophil' => self::decimal($input['eosinophil'] ?? null, 'Eosinofil', 100, 2),
            'basophil' => self::decimal($input['basophil'] ?? null, 'Basofil', 100, 2),
            'neutrophil' => self::decimal($input['neutrophil'] ?? null, 'Neutrofil', 100, 2),
            'lymphocyte' => self::decimal($input['lymphocyte'] ?? null, 'Limfosit', 100, 2),
            'monocyte' => self::decimal($input['monocyte'] ?? null, 'Monosit', 100, 2),
            'gds' => self::decimal($input['gds'] ?? null, 'GDS', 5000, 2),
            'fasting_glucose' => self::decimal($input['fasting_glucose'] ?? null, 'Glukosa darah puasa', 5000, 2),
            'glucose_2h_pp' => self::decimal($input['glucose_2h_pp'] ?? null, 'Glukosa darah 2 jam PP', 5000, 2),
            'urea' => self::decimal($input['urea'] ?? null, 'Ureum', 1000, 2),
            'creatinine' => self::decimal($input['creatinine'] ?? null, 'Kreatinin', 100, 2),
            'cholesterol' => self::decimal($input['cholesterol'] ?? null, 'Kolesterol', 5000, 2),
            'uric_acid' => self::decimal($input['uric_acid'] ?? null, 'Asam urat', 100, 2),
            'sgot' => self::decimal($input['sgot'] ?? null, 'SGOT', 10000, 2),
            'sgpt' => self::decimal($input['sgpt'] ?? null, 'SGPT', 10000, 2),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private static function urineData(array $input): array
    {
        $qualitativeFields = ['urine_color' => 'color', 'urine_clarity' => 'clarity', 'protein' => 'protein', 'glucose_reduction' => 'glucose_reduction', 'urobilinogen' => 'urobilinogen', 'bilirubin' => 'bilirubin', 'occult_blood' => 'occult_blood', 'ketone' => 'ketone', 'nitrite' => 'nitrite', 'leukocyte_esterase' => 'leukocyte_esterase', 'erythrocytes' => 'erythrocytes', 'leukocytes' => 'leukocytes', 'squamous_cells' => 'squamous_cells', 'casts' => 'casts', 'crystals' => 'crystals', 'bacteria' => 'bacteria'];
        $data = [
            'specific_gravity' => self::decimal($input['urine_specific_gravity'] ?? null, 'Berat jenis urine', 10, 3),
            'ph' => self::decimal($input['urine_ph'] ?? null, 'pH urine', 14, 1),
        ];
        foreach ($qualitativeFields as $inputKey => $column) {
            $data[$column] = self::nullableText($input[$inputKey] ?? null, 100);
        }

        return $data;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private static function drugData(array $input): array
    {
        return [
            'amphetamine' => self::nullableText($input['drug_amphetamine'] ?? null, 30),
            'marijuana' => self::nullableText($input['drug_marijuana'] ?? null, 30),
            'morphine' => self::nullableText($input['drug_morphine'] ?? null, 30),
            'benzodiazepine' => self::nullableText($input['drug_benzodiazepine'] ?? null, 30),
            'methamphetamine' => self::nullableText($input['drug_methamphetamine'] ?? null, 30),
            'cocaine' => self::nullableText($input['drug_cocaine'] ?? null, 30),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private static function widalData(array $input): array
    {
        return [
            'widal_o' => self::nullableText($input['widal_o'] ?? null, 50),
            'widal_ao' => self::nullableText($input['widal_ao'] ?? null, 50),
            'widal_bo' => self::nullableText($input['widal_bo'] ?? null, 50),
            'widal_h' => self::nullableText($input['widal_h'] ?? null, 50),
        ];
    }

    private static function nullableText(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maxLength) {
            throw new ValidationException(['form' => 'Salah satu nilai teks terlalu panjang.']);
        }

        return $value;
    }

    private static function requiredText(mixed $value, string $label, int $maxLength): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new ValidationException(['room' => $label . ' wajib diisi.']);
        }
        if (mb_strlen($value) > $maxLength) {
            throw new ValidationException(['room' => $label . ' terlalu panjang.']);
        }

        return $value;
    }

    private static function decimal(mixed $value, string $label, float $maximum, int $scale): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            $normalised = NumberFormat::normalize($value, $label);
        } catch (ValidationException) {
            throw new ValidationException(['form' => $label . ' harus menggunakan format angka Indonesia (contoh 1.000 atau 1,5).']);
        }
        if ((float) $normalised < 0 || (float) $normalised > $maximum) {
            throw new ValidationException(['form' => $label . ' tidak valid.']);
        }

        return number_format((float) $normalised, $scale, '.', '');
    }

    /** @param list<string> $allowed */
    private static function choice(mixed $value, string $label, array $allowed): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!in_array($value, $allowed, true)) {
            throw new ValidationException(['form' => $label . ' tidak valid.']);
        }

        return $value;
    }

    private static function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function time(mixed $value): string
    {
        $value = trim((string) $value);
        $time = DateTimeImmutable::createFromFormat('!H:i', $value);
        if ($value === '' || $time === false || $time->format('H:i') !== $value) {
            throw new ValidationException(['examined_time' => 'Jam periksa harus menggunakan format HH:MM.']);
        }

        return $time->format('H:i:s');
    }
}
