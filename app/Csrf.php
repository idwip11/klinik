<?php

declare(strict_types=1);

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    public static function verify(?string $token): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? '';

        return is_string($token) && is_string($expected) && hash_equals($expected, $token);
    }
}

