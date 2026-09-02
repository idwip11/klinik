<?php

declare(strict_types=1);

final class NumberFormat
{
    public static function normalize(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!self::isIndonesianNumber($value)) {
            throw new ValidationException([
                'form' => 'Hasil ' . $label . ' harus menggunakan format angka Indonesia (contoh 1.000 atau 1,5).',
            ]);
        }

        $parts = explode(',', $value, 2);
        $integer = str_replace('.', '', $parts[0]);
        $decimal = $parts[1] ?? '';
        $integer = ltrim($integer, '0') ?: '0';
        $decimal = rtrim($decimal, '0');

        return $integer . ($decimal === '' ? '' : '.' . $decimal);
    }

    public static function isIndonesianNumber(string $value): bool
    {
        return preg_match('/^\d+(?:,\d{1,6})?$/', $value) === 1
            || preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d{1,6})?$/', $value) === 1;
    }
}
