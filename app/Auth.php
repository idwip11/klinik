<?php

declare(strict_types=1);

final class Auth
{
    /** @return array{id: int, full_name: string, username: string, role: string}|null */
    public static function user(): ?array
    {
        $user = $_SESSION['user'] ?? null;

        return is_array($user) ? $user : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function attempt(string $username, string $password): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT id, full_name, username, password_hash, role
             FROM users
             WHERE username = :username AND is_active = 1
             LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        if (!is_array($user) || !password_verify($password, (string) $user['password_hash'])) {
            AuditLogger::record('auth.login_failed', null, 'user', null, null, ['username' => $username]);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'full_name' => (string) $user['full_name'],
            'username' => (string) $user['username'],
            'role' => (string) $user['role'],
        ];

        Database::connection()->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id')
            ->execute(['id' => (int) $user['id']]);
        AuditLogger::record('auth.login_success', (int) $user['id'], 'user', (int) $user['id']);

        return true;
    }

    public static function logout(): void
    {
        $user = self::user();
        if ($user !== null) {
            AuditLogger::record('auth.logout', $user['id'], 'user', $user['id']);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], (bool) $parameters['secure'], (bool) $parameters['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }

        Flash::set('error', 'Silakan masuk terlebih dahulu.');
        self::redirect('/');
    }

    /** @param list<string> $roles */
    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        $role = self::user()['role'] ?? '';
        if (in_array($role, $roles, true)) {
            return;
        }

        http_response_code(403);
        View::render('errors/403', ['pageTitle' => 'Akses ditolak']);
        exit;
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}

