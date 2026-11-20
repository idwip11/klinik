<?php

declare(strict_types=1);

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = []): void
    {
        $viewFile = ROOT_PATH . '/app/views/' . $template . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException('Template tidak ditemukan.');
        }

        extract($data, EXTR_SKIP);
        $pageTitle = $pageTitle ?? 'Klinik Lab';
        $flash = Flash::pull();
        require ROOT_PATH . '/app/views/layout.php';
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function formatDate(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if ($value === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $date->format('d/m/Y') : $value;
    }

    public static function formatLongDate(mixed $value): string
    {
        $value = (string) ($value ?? '');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return $value;
        }

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $date->format('d') . ' ' . $months[(int) $date->format('n')] . ' ' . $date->format('Y');
    }

    public static function formatDateForInput(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date->format('d-m-Y') : $value;
    }

    public static function formatTime(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? '' : substr($value, 0, 5);
    }

    /** Age on the examination date, not the date the document is opened. */
    public static function formatAge(mixed $birthDate, mixed $examinedOn): string
    {
        $birthDate = trim((string) ($birthDate ?? ''));
        $examinedOn = trim((string) ($examinedOn ?? ''));
        $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
        $examined = DateTimeImmutable::createFromFormat('!Y-m-d', $examinedOn);
        if ($birth === false || $examined === false
            || $birth->format('Y-m-d') !== $birthDate
            || $examined->format('Y-m-d') !== $examinedOn
            || $birth > $examined) {
            return '—';
        }
        $age = $birth->diff($examined);
        return $age->y . ' tahun ' . $age->m . ' bulan ' . $age->d . ' hari';
    }

    public static function formatNumber(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return $value;
        }

        $negative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '+-');
        if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d+)?$/', $unsigned) === 1) {
            $normalised = str_replace(',', '.', str_replace('.', '', $unsigned));
        } elseif (preg_match('/^\d+,\d+$/', $unsigned) === 1) {
            $normalised = str_replace(',', '.', $unsigned);
        } else {
            $normalised = $unsigned;
        }
        if (!is_numeric($normalised)) {
            return $value;
        }

        [$integer, $decimal] = array_pad(explode('.', $normalised, 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';
        $decimal = rtrim($decimal, '0');
        $formatted = number_format((int) $integer, 0, ',', '.');

        return ($negative ? '-' : '') . $formatted . ($decimal === '' ? '' : ',' . $decimal);
    }
}
