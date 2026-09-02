<?php

declare(strict_types=1);

final class AuditLogger
{
    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    public static function record(
        string $event,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        $statement = Database::connection()->prepare(
            'INSERT INTO audit_logs
                (user_id, event, entity_type, entity_id, previous_values, new_values, ip_address, created_at)
             VALUES
                (:user_id, :event, :entity_type, :entity_id, :previous_values, :new_values, :ip_address, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'user_id' => $userId,
            'event' => $event,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'previous_values' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'new_values' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'ip_address' => self::clientIp(),
        ]);
    }

    private static function clientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }
}

