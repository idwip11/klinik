<?php

declare(strict_types=1);

final class LegacyImportService
{
    private const ISSUE_SAMPLE_LIMIT = 200;

    public function __construct(
        private ?PDO $pdo = null,
        private readonly LegacyImportMapper $mapper = new LegacyImportMapper()
    ) {
    }

    /** @return array<string, mixed> */
    public function run(string $file, string $type, string $sourceSheet, bool $dryRun, ?int $userId = null): array
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new InvalidArgumentException('Berkas CSV tidak dapat dibaca.');
        }
        if (!in_array($type, ['mcu_general', 'drug_screen'], true)) {
            throw new InvalidArgumentException('Jenis impor harus mcu_general atau drug_screen.');
        }
        $sourceSheet = trim($sourceSheet);
        if ($sourceSheet === '' || mb_strlen($sourceSheet) > 100) {
            throw new InvalidArgumentException('Nama sheet sumber wajib diisi (maksimal 100 karakter).');
        }
        if (!$dryRun) {
            $this->pdo ??= Database::connection();
            if (!$userId || $userId < 1) {
                throw new InvalidArgumentException('User ID wajib untuk impor aktual.');
            }
            $this->assertImportUser($userId);
        }

        $sha256 = hash_file('sha256', $file);
        if ($sha256 === false) {
            throw new RuntimeException('Hash berkas sumber tidak dapat dibuat.');
        }

        $csv = $this->openCsv($file);
        $headers = $this->readHeaders($csv['handle'], $csv['delimiter']);
        $this->validateHeaders($headers, $type);

        $summary = [
            'mode' => $dryRun ? 'dry-run' : 'import',
            'batch_id' => null,
            'source_filename' => basename($file),
            'source_sheet' => $sourceSheet,
            'source_sha256' => $sha256,
            'total_rows' => 0,
            'valid_rows' => 0,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
            'warning_count' => 0,
            'issue_samples' => [],
        ];

        $batchId = null;
        if (!$dryRun) {
            $batchId = $this->createBatch(basename($file), $sourceSheet, $sha256, $type, (int) $userId);
            $summary['batch_id'] = $batchId;
        }

        try {
            $rowNumber = 1;
            while (($values = fgetcsv($csv['handle'], 0, $csv['delimiter'], '"', '\\')) !== false) {
                $rowNumber++;
                if ($this->isBlankRow($values)) {
                    continue;
                }

                $summary['total_rows']++;
                $row = $this->combineRow($headers, $values);
                $fingerprint = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                $mapped = $this->mapper->map($row, $type);

                foreach ($mapped['warnings'] as $issue) {
                    $summary['warning_count']++;
                    $this->captureIssue($summary, $batchId, $rowNumber, 'warning', $issue, $fingerprint);
                }

                if ($mapped['errors'] !== []) {
                    $summary['failed_rows']++;
                    foreach ($mapped['errors'] as $issue) {
                        $this->captureIssue($summary, $batchId, $rowNumber, 'error', $issue, $fingerprint);
                    }
                    continue;
                }

                $summary['valid_rows']++;
                if ($dryRun) {
                    continue;
                }

                try {
                    $result = $this->importRow($mapped, $sha256, $sourceSheet, $rowNumber, (int) $batchId, (int) $userId);
                    $summary[$result === 'skipped' ? 'skipped_rows' : 'imported_rows']++;
                } catch (DomainException $exception) {
                    $summary['failed_rows']++;
                    $issue = ['field' => 'row', 'code' => 'identity_conflict', 'message' => $exception->getMessage()];
                    $this->captureIssue($summary, $batchId, $rowNumber, 'error', $issue, $fingerprint);
                } catch (Throwable $exception) {
                    error_log('Legacy import row failed: ' . $exception->getCode());
                    $summary['failed_rows']++;
                    $issue = ['field' => 'row', 'code' => 'database_error', 'message' => 'Baris gagal disimpan; periksa log server.'];
                    $this->captureIssue($summary, $batchId, $rowNumber, 'error', $issue, $fingerprint);
                }
            }

            if (!$dryRun) {
                $this->finishBatch((int) $batchId, 'completed', $summary);
                AuditLogger::record('legacy_import.completed', (int) $userId, 'import_batch', (int) $batchId, null, [
                    'source_sha256' => $sha256,
                    'source_sheet' => $sourceSheet,
                    'total_rows' => $summary['total_rows'],
                    'imported_rows' => $summary['imported_rows'],
                    'skipped_rows' => $summary['skipped_rows'],
                    'failed_rows' => $summary['failed_rows'],
                ]);
            }
        } catch (Throwable $exception) {
            if ($batchId !== null) {
                $this->finishBatch($batchId, 'failed', $summary);
            }
            throw $exception;
        } finally {
            fclose($csv['handle']);
        }

        return $summary;
    }

    /** @return array{handle:resource,delimiter:string} */
    private function openCsv(string $file): array
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Berkas CSV gagal dibuka.');
        }
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw new InvalidArgumentException('Berkas CSV kosong.');
        }
        rewind($handle);

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        return ['handle' => $handle, 'delimiter' => $delimiter];
    }

    /** @param resource $handle @return list<string> */
    private function readHeaders($handle, string $delimiter): array
    {
        $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if ($headers === false) {
            throw new InvalidArgumentException('Header CSV tidak dapat dibaca.');
        }

        $normalized = [];
        foreach ($headers as $index => $header) {
            $header = $index === 0 ? preg_replace('/^\xEF\xBB\xBF/', '', $header) : $header;
            $key = strtolower(trim((string) preg_replace('/\s+/u', ' ', $header)));
            if ($key === '') {
                $key = '__empty_' . $index;
            }
            if (in_array($key, $normalized, true)) {
                throw new InvalidArgumentException("Header CSV duplikat: {$key}");
            }
            $normalized[] = $key;
        }
        return $normalized;
    }

    /** @param list<string> $headers */
    private function validateHeaders(array $headers, string $type): void
    {
        $groups = $type === 'mcu_general'
            ? [['no rm', 'no. rm'], ['no. lab', 'nomor lab', 'no lab'], ['nama'], ['tanggal periksa']]
            : [['no rm', 'no. rm'], ['nomor lab', 'no. lab', 'no lab'], ['nama'], ['tanggal periksa']];

        foreach ($groups as $aliases) {
            if (array_intersect($aliases, $headers) === []) {
                throw new InvalidArgumentException('Header wajib tidak ditemukan: ' . implode(' / ', $aliases));
            }
        }
    }

    /** @param list<string> $headers @param list<string|null> $values @return array<string, string|null> */
    private function combineRow(array $headers, array $values): array
    {
        $values = array_slice(array_pad($values, count($headers), null), 0, count($headers));
        return array_combine($headers, $values) ?: [];
    }

    /** @param list<string|null> $values */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function assertImportUser(int $userId): void
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE id = :id AND is_active = 1 AND role = 'admin'"
        );
        $statement->execute(['id' => $userId]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new InvalidArgumentException('Impor aktual harus dijalankan atas nama supervisor/admin aktif.');
        }
    }

    private function createBatch(string $filename, string $sheet, string $sha256, string $type, int $userId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO import_batches
                (source_filename, source_sheet, source_sha256, examination_type, status, started_by, started_at, created_at)
             VALUES (:filename, :sheet, :sha256, :type, \'importing\', :user_id, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute(compact('filename', 'sheet', 'sha256', 'type') + ['user_id' => $userId]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $summary */
    private function finishBatch(int $batchId, string $status, array $summary): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE import_batches SET status = :status, total_rows = :total_rows, imported_rows = :imported_rows,
                skipped_rows = :skipped_rows, failed_rows = :failed_rows, warning_count = :warning_count,
                completed_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'total_rows' => $summary['total_rows'],
            'imported_rows' => $summary['imported_rows'],
            'skipped_rows' => $summary['skipped_rows'],
            'failed_rows' => $summary['failed_rows'],
            'warning_count' => $summary['warning_count'],
            'id' => $batchId,
        ]);
    }

    /**
     * @param array<string, mixed> $summary
     * @param array{field:string,code:string,message:string} $issue
     */
    private function captureIssue(array &$summary, ?int $batchId, int $rowNumber, string $severity, array $issue, string $fingerprint): void
    {
        if (count($summary['issue_samples']) < self::ISSUE_SAMPLE_LIMIT) {
            $summary['issue_samples'][] = [
                'row' => $rowNumber,
                'severity' => $severity,
                'field' => $issue['field'],
                'code' => $issue['code'],
                'message' => $issue['message'],
            ];
        }
        if ($batchId === null) {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO import_errors
                (import_batch_id, source_row_number, severity, field_name, error_code, message, row_fingerprint, created_at)
             VALUES (:batch_id, :row_number, :severity, :field, :code, :message, :fingerprint, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'batch_id' => $batchId,
            'row_number' => $rowNumber,
            'severity' => $severity,
            'field' => $issue['field'],
            'code' => $issue['code'],
            'message' => $issue['message'],
            'fingerprint' => $fingerprint,
        ]);
    }

    /** @param array<string, mixed> $mapped */
    private function importRow(array $mapped, string $sourceHash, string $sheet, int $rowNumber, int $batchId, int $userId): string
    {
        $labNumber = (string) $mapped['examination']['lab_number'];
        $legacyKey = hash('sha256', $sourceHash . '|' . $sheet . '|' . $rowNumber . '|' . $labNumber);

        $existing = $this->pdo->prepare('SELECT id FROM examinations WHERE legacy_source_key = :key OR lab_number = :lab LIMIT 1');
        $existing->execute(['key' => $legacyKey, 'lab' => $labNumber]);
        if ($existing->fetchColumn() !== false) {
            return 'skipped';
        }

        $this->pdo->beginTransaction();
        try {
            $patientId = $this->findOrCreatePatient($mapped['patient']);
            $exam = $mapped['examination'];
            $statement = $this->pdo->prepare(
                'INSERT INTO examinations
                    (patient_id, lab_number, examination_type, status, examined_on, examined_time, condition_note, letter_number, purpose,
                     import_batch_id, source_sheet, source_row_number, legacy_source_key, created_by, updated_by, created_at, updated_at)
                 VALUES
                    (:patient_id, :lab_number, :examination_type, \'draft\', :examined_on, :examined_time, :condition_note, :letter_number, :purpose,
                     :batch_id, :source_sheet, :source_row_number, :legacy_key, :created_by, :updated_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $statement->execute([
                'patient_id' => $patientId,
                'lab_number' => $exam['lab_number'],
                'examination_type' => $exam['examination_type'],
                'examined_on' => $exam['examined_on'],
                'examined_time' => $exam['examined_time'],
                'condition_note' => $exam['condition_note'],
                'letter_number' => $exam['letter_number'],
                'purpose' => $exam['purpose'],
                'batch_id' => $batchId,
                'source_sheet' => $sheet,
                'source_row_number' => $rowNumber,
                'legacy_key' => $legacyKey,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $examinationId = (int) $this->pdo->lastInsertId();

            $this->insertResult('vital_results', $examinationId, $mapped['vital']);
            $this->insertResult('blood_results', $examinationId, $mapped['blood']);
            $this->insertResult('urine_results', $examinationId, $mapped['urine']);
            $this->insertResult('widal_results', $examinationId, $mapped['widal']);
            $this->insertResult('drug_results', $examinationId, $mapped['drug']);

            $this->pdo->commit();
            return 'imported';
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $patient */
    private function findOrCreatePatient(array $patient): int
    {
        $statement = $this->pdo->prepare('SELECT * FROM patients WHERE medical_record_number = :mrn LIMIT 1');
        $statement->execute(['mrn' => $patient['medical_record_number']]);
        $existing = $statement->fetch();
        if (is_array($existing)) {
            if (mb_strtolower(trim((string) $existing['full_name'])) !== mb_strtolower(trim((string) $patient['full_name']))) {
                throw new DomainException('Nomor RM sudah terhubung ke nama pasien berbeda; baris tidak diimpor.');
            }
            if ($patient['national_id'] !== null && $existing['national_id'] !== null && $patient['national_id'] !== $existing['national_id']) {
                throw new DomainException('Nomor RM sudah terhubung ke NIK berbeda; baris tidak diimpor.');
            }
            if ($patient['birth_date'] !== null && $existing['birth_date'] !== null && $patient['birth_date'] !== $existing['birth_date']) {
                throw new DomainException('Nomor RM sudah terhubung ke tanggal lahir berbeda; baris tidak diimpor.');
            }
            if ($patient['sex'] !== null && $existing['sex'] !== null && $patient['sex'] !== $existing['sex']) {
                throw new DomainException('Nomor RM sudah terhubung ke jenis kelamin berbeda; baris tidak diimpor.');
            }
            if ($patient['national_id'] !== null && $existing['national_id'] === null) {
                $nikCheck = $this->pdo->prepare('SELECT id FROM patients WHERE national_id = :national_id AND id <> :id LIMIT 1');
                $nikCheck->execute(['national_id' => $patient['national_id'], 'id' => $existing['id']]);
                if ($nikCheck->fetchColumn() !== false) {
                    throw new DomainException('NIK sudah terhubung ke nomor RM berbeda; baris tidak diimpor.');
                }
            }

            $fill = $this->pdo->prepare(
                'UPDATE patients SET
                    national_id = COALESCE(national_id, :national_id),
                    birth_place = COALESCE(birth_place, :birth_place),
                    birth_date = COALESCE(birth_date, :birth_date),
                    sex = COALESCE(sex, :sex),
                    address = COALESCE(address, :address),
                    phone = COALESCE(phone, :phone),
                    occupation = COALESCE(occupation, :occupation),
                    updated_at = UTC_TIMESTAMP()
                 WHERE id = :id'
            );
            $fill->execute([
                'national_id' => $patient['national_id'],
                'birth_place' => $patient['birth_place'],
                'birth_date' => $patient['birth_date'],
                'sex' => $patient['sex'],
                'address' => $patient['address'],
                'phone' => $patient['phone'],
                'occupation' => $patient['occupation'],
                'id' => $existing['id'],
            ]);
            return (int) $existing['id'];
        }

        if ($patient['national_id'] !== null) {
            $statement = $this->pdo->prepare('SELECT id FROM patients WHERE national_id = :national_id LIMIT 1');
            $statement->execute(['national_id' => $patient['national_id']]);
            if ($statement->fetchColumn() !== false) {
                throw new DomainException('NIK sudah terhubung ke nomor RM berbeda; baris tidak diimpor.');
            }
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO patients
                (medical_record_number, national_id, full_name, birth_place, birth_date, sex, address, phone, occupation, created_at, updated_at)
             VALUES
                (:medical_record_number, :national_id, :full_name, :birth_place, :birth_date, :sex, :address, :phone, :occupation,
                 UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $statement->execute($patient);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $values */
    private function insertResult(string $table, int $examinationId, array $values): void
    {
        if ($values === []) {
            return;
        }

        $allowed = [
            'vital_results' => ['blood_pressure_systolic', 'blood_pressure_diastolic', 'height_cm', 'weight_kg', 'respiratory_rate', 'color_vision', 'pulse_rate', 'blood_group', 'rhesus', 'temperature_c'],
            'blood_results' => ['hgb', 'wbc', 'plt', 'rbc', 'hct', 'lymph_pct', 'mid_pct', 'gran_pct', 'mcv', 'mch', 'mchc', 'eosinophil', 'basophil', 'neutrophil', 'lymphocyte', 'monocyte', 'gds', 'fasting_glucose', 'glucose_2h_pp', 'urea', 'creatinine', 'cholesterol', 'uric_acid', 'sgot', 'sgpt'],
            'urine_results' => ['color', 'clarity', 'specific_gravity', 'ph', 'protein', 'glucose_reduction', 'urobilinogen', 'bilirubin', 'occult_blood', 'ketone', 'nitrite', 'leukocyte_esterase', 'erythrocytes', 'leukocytes', 'squamous_cells', 'casts', 'crystals', 'bacteria'],
            'widal_results' => ['widal_o', 'widal_ao', 'widal_bo', 'widal_h'],
            'drug_results' => ['amphetamine', 'marijuana', 'morphine', 'benzodiazepine', 'methamphetamine', 'cocaine'],
        ];
        if (!isset($allowed[$table])) {
            throw new LogicException('Tabel hasil tidak diizinkan.');
        }

        $filtered = array_intersect_key($values, array_flip($allowed[$table]));
        $columns = array_keys($filtered);
        $sqlColumns = implode(', ', array_merge(['examination_id'], $columns, ['created_at', 'updated_at']));
        $placeholders = implode(', ', array_merge([':examination_id'], array_map(static fn (string $column): string => ':' . $column, $columns), ['UTC_TIMESTAMP()', 'UTC_TIMESTAMP()']));
        $statement = $this->pdo->prepare("INSERT INTO {$table} ({$sqlColumns}) VALUES ({$placeholders})");
        $statement->execute(['examination_id' => $examinationId] + $filtered);
    }
}
