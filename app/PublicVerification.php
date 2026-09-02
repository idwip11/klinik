<?php

declare(strict_types=1);

final class PublicVerification
{
    /** @param array<string,mixed> $examination */
    public static function url(array $examination): ?string
    {
        $id = (int) ($examination['id'] ?? 0);
        if ($id < 1 || ($examination['examination_type'] ?? null) !== 'drug_screen'
            || !in_array($examination['status'] ?? null, ['completed', 'finalized'], true)) {
            return null;
        }

        $baseUrl = rtrim((string) Env::get('APP_URL', ''), '/');
        if ($baseUrl === '') {
            return null;
        }

        return $baseUrl . '/verification/examinations/' . $id . '?token=' . self::token($id);
    }

    public static function isValid(int $examinationId, string $token): bool
    {
        return $examinationId > 0 && $token !== '' && hash_equals(self::token($examinationId), $token);
    }

    private static function token(int $examinationId): string
    {
        $secret = trim((string) Env::get('APP_PUBLIC_LINK_SECRET', ''));
        if ($secret === '') {
            $secret = 'local-development-only:' . ROOT_PATH;
        }

        return hash_hmac('sha256', 'drug-screen-examination:' . $examinationId, $secret);
    }
}
