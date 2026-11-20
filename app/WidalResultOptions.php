<?php

declare(strict_types=1);

final class WidalResultOptions
{
    public const VALUES = ['Negatif', '(+) 1/80', '(+) 1/160', '(+) 1/320', '(+) 1/640'];

    public const FIELDS = [
        'WIDAL_O' => 'widal_o',
        'WIDAL_AO' => 'widal_ao',
        'WIDAL_BO' => 'widal_bo',
        'WIDAL_H' => 'widal_h',
    ];

    /** @return list<string> */
    public static function forValue(?string $currentValue): array
    {
        $options = self::VALUES;
        $currentValue = trim((string) $currentValue);
        // Keep an existing free-text result selectable without changing its meaning.
        if ($currentValue !== '' && !in_array($currentValue, $options, true)) {
            $options[] = $currentValue;
        }

        return $options;
    }
}
