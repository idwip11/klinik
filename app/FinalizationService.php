<?php

declare(strict_types=1);

final class FinalizationService
{
    public static function saveImmediately(int $examinationId, int $userId): void
    {
        $examination = ExaminationRepository::find($examinationId);
        if ($examination === false) {
            throw new RuntimeException('Pemeriksaan tidak ditemukan.');
        }

        $flags = ReferenceEvaluator::evaluate($examination);
        self::storeSnapshot($examinationId, $userId, $flags);
        AuditLogger::record('examination.saved', $userId, 'examination', $examinationId, null, ['status' => 'finalized', 'results' => count($flags)]);
    }

    public static function finalize(int $examinationId, int $userId): void
    {
        $examination = ExaminationRepository::find($examinationId);
        if ($examination === false) {
            throw new RuntimeException('Pemeriksaan tidak ditemukan.');
        }
        if ($examination['status'] !== 'completed') {
            throw new ValidationException(['form' => 'Hanya pemeriksaan berstatus selesai yang dapat difinalisasi.']);
        }

        $flags = ReferenceEvaluator::evaluate($examination);
        if ($flags === []) {
            throw new ValidationException(['form' => 'Minimal satu hasil pemeriksaan harus diisi sebelum finalisasi.']);
        }
        $unassessedFlags = array_values(array_filter($flags, static fn(array $flag): bool => $flag['interpretation'] === 'not_assessed'));
        if ($unassessedFlags !== []) {
            $labels = array_map(static fn(array $flag): string => (string) $flag['display_name'], $unassessedFlags);
            throw new ValidationException(['form' => 'Lengkapi rentang referensi sebelum finalisasi: ' . implode(', ', $labels) . '.']);
        }

        self::storeSnapshot($examinationId, $userId, $flags);
        AuditLogger::record('examination.finalized', $userId, 'examination', $examinationId, ['status' => 'completed'], ['status' => 'finalized', 'results' => count($flags)]);
    }

    /** @param list<array<string, mixed>> $flags */
    private static function storeSnapshot(int $examinationId, int $userId, array $flags): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM examination_result_flags WHERE examination_id = :id')->execute(['id' => $examinationId]);
            $statement = $pdo->prepare(
                'INSERT INTO examination_result_flags
                    (examination_id, test_code, display_name, section, raw_value, unit, reference_range_id, reference_sex, reference_text, interpretation, created_at)
                 VALUES
                    (:examination_id, :test_code, :display_name, :section, :raw_value, :unit, :reference_range_id, :reference_sex, :reference_text, :interpretation, UTC_TIMESTAMP())'
            );
            foreach ($flags as $flag) {
                $statement->execute(array_merge(['examination_id' => $examinationId], $flag));
            }

            $pdo->prepare(
                'UPDATE examinations
                 SET status = "finalized", finalized_by = :finalized_by, finalized_at = UTC_TIMESTAMP(), updated_by = :updated_by, updated_at = UTC_TIMESTAMP()
                 WHERE id = :id'
            )->execute(['finalized_by' => $userId, 'updated_by' => $userId, 'id' => $examinationId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public static function flags(int $examinationId, bool $snapshotOnly = false): array
    {
        $statement = Database::connection()->prepare(
            'SELECT test_code, display_name, section, raw_value, unit, reference_sex, reference_text, interpretation
             FROM examination_result_flags WHERE examination_id = :id ORDER BY section, id'
        );
        $statement->execute(['id' => $examinationId]);
        $flags = $statement->fetchAll();
        if ($flags !== [] || $snapshotOnly) {
            return $flags;
        }

        $examination = ExaminationRepository::find($examinationId);
        return $examination === false ? [] : ReferenceEvaluator::evaluate($examination);
    }

    public static function recordPrint(int $examinationId, string $documentType, int $userId): void
    {
        $isPanelDocument = $documentType === 'panel_results';
        if (!in_array($documentType, ['blood_general', 'urine_complete', 'newborn_blood', 'drug_screen', 'panel_results'], true)) {
            throw new ValidationException(['form' => 'Jenis dokumen tidak valid.']);
        }
        $examination = ExaminationRepository::find($examinationId);
        if ($examination === false || !in_array($examination['status'], ['draft', 'completed', 'finalized'], true)) {
            throw new ValidationException(['form' => 'Hasil pemeriksaan tidak dapat dicetak.']);
        }
        if (!$isPanelDocument && !in_array($documentType, ExaminationType::documentTypes((string) $examination['examination_type']), true)) {
            throw new ValidationException(['form' => 'Jenis dokumen tidak sesuai dengan pemeriksaan.']);
        }
        if ($isPanelDocument && (
            $examination['examination_type'] !== 'mcu_general'
            || ExaminationPanelCatalogRepository::forExamination($examinationId) === []
        )) {
            throw new ValidationException(['form' => 'Cetak berbasis panel hanya tersedia untuk pemeriksaan yang memiliki panel.']);
        }

        Database::connection()->prepare(
            'INSERT INTO print_logs (examination_id, printed_by, document_type, printed_at)
             VALUES (:examination_id, :printed_by, :document_type, UTC_TIMESTAMP())'
        )->execute(['examination_id' => $examinationId, 'printed_by' => $userId, 'document_type' => $documentType]);
        AuditLogger::record('examination.printed', $userId, 'examination', $examinationId, null, ['document_type' => $documentType]);
    }
}
