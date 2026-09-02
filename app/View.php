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

    public static function formatTime(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? '' : substr($value, 0, 5);
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
