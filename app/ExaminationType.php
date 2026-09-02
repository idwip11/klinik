<?php

declare(strict_types=1);

final class ExaminationType
{
    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'mcu_general' => 'MCU umum',
            'newborn_blood' => 'Darah bayi',
            'drug_screen' => 'Skrining narkoba',
        ];
    }

    public static function isValid(string $type): bool
    {
        return array_key_exists($type, self::labels());
    }

    /** @return list<string> */
    public static function documentTypes(string $type): array
    {
        return match ($type) {
            'newborn_blood' => ['newborn_blood'],
            'drug_screen' => ['drug_screen'],
            default => ['blood_general', 'urine_complete'],
        };
    }
}

