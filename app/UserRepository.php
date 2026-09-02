<?php

declare(strict_types=1);

final class UserRepository
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return Database::connection()->query('SELECT id, full_name, username, role, is_active, last_login_at, created_at FROM users ORDER BY is_active DESC, full_name ASC')->fetchAll();
    }

    /** @param array<string, mixed> $input */
    public static function create(array $input, int $actorId): void
    {
        $data = self::validate($input, true);
        $statement = Database::connection()->prepare('INSERT INTO users (full_name, username, password_hash, role, is_active, created_at, updated_at) VALUES (:full_name, :username, :password_hash, :role, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())');
        self::execute($statement, $data);
        AuditLogger::record('user.created', $actorId, 'user', (int) Database::connection()->lastInsertId(), null, ['username' => $data['username'], 'role' => $data['role']]);
    }

    /** @param array<string, mixed> $input */
    public static function update(int $id, array $input, int $actorId): void
    {
        $current = self::find($id);
        if ($current === false) {
            throw new ValidationException(['form' => 'Pengguna tidak ditemukan.']);
        }
        $data = self::validate($input, false);
        $isActive = isset($input['is_active']) && (string) $input['is_active'] === '1' ? 1 : 0;
        if ($id === $actorId && ($isActive !== 1 || $data['role'] !== 'admin')) {
            throw new ValidationException(['form' => 'Anda tidak dapat menonaktifkan atau menurunkan peran akun sendiri.']);
        }
        if ((string) $current['role'] === 'admin' && (int) $current['is_active'] === 1 && ($isActive !== 1 || $data['role'] !== 'admin') && self::activeAdminCount() <= 1) {
            throw new ValidationException(['form' => 'Minimal satu administrator aktif harus dipertahankan.']);
        }
        $sql = 'UPDATE users SET full_name = :full_name, username = :username, role = :role, is_active = :is_active, updated_at = UTC_TIMESTAMP()';
        $params = ['id' => $id, 'full_name' => $data['full_name'], 'username' => $data['username'], 'role' => $data['role'], 'is_active' => $isActive];
        if ($data['password_hash'] !== null) { $sql .= ', password_hash = :password_hash'; $params['password_hash'] = $data['password_hash']; }
        $sql .= ' WHERE id = :id';
        self::execute(Database::connection()->prepare($sql), $params);
        AuditLogger::record('user.updated', $actorId, 'user', $id, ['role' => $current['role'], 'is_active' => $current['is_active']], ['role' => $data['role'], 'is_active' => $isActive]);
    }

    public static function delete(int $id, int $actorId): void
    {
        if ($id === $actorId) {
            throw new ValidationException(['form' => 'Anda tidak dapat menghapus akun yang sedang digunakan.']);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare('SELECT id, full_name, username, role, is_active FROM users WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $id]);
            $current = $statement->fetch();
            if (!is_array($current)) {
                throw new ValidationException(['form' => 'Pengguna tidak ditemukan.']);
            }

            $activeAdmins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            if ((string) $current['role'] === 'admin' && (int) $current['is_active'] === 1 && count($activeAdmins) <= 1) {
                throw new ValidationException(['form' => 'Akun administrator aktif terakhir tidak dapat dihapus.']);
            }

            AuditLogger::record(
                'user.deleted',
                $actorId,
                'user',
                $id,
                [
                    'full_name' => $current['full_name'],
                    'username' => $current['username'],
                    'role' => $current['role'],
                    'is_active' => (int) $current['is_active'],
                ]
            );
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
            $pdo->commit();
        } catch (ValidationException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() === '23000') {
                throw new ValidationException(['form' => 'Akun tidak dapat dihapus karena sudah digunakan pada riwayat operasional. Nonaktifkan akun agar riwayat tetap tersimpan.']);
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed>|false */
    private static function find(int $id): array|false { $s = Database::connection()->prepare('SELECT * FROM users WHERE id = :id'); $s->execute(['id' => $id]); return $s->fetch(); }
    private static function activeAdminCount(): int { return (int) Database::connection()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn(); }
    /** @param array<string,mixed> $input @return array{full_name:string,username:string,role:string,password_hash:?string} */
    private static function validate(array $input, bool $passwordRequired): array
    {
        $name = trim((string) ($input['full_name'] ?? '')); $username = trim((string) ($input['username'] ?? '')); $role = (string) ($input['role'] ?? ''); $password = (string) ($input['password'] ?? '');
        $errors = [];
        if ($name === '' || mb_strlen($name) > 150) $errors['full_name'] = 'Nama wajib diisi (maksimal 150 karakter).';
        if ($username === '' || mb_strlen($username) > 64 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) $errors['username'] = 'Username hanya boleh huruf, angka, titik, garis bawah, atau strip.';
        if (!in_array($role, ['admin', 'perawat'], true)) $errors['role'] = 'Peran tidak valid.';
        if (($passwordRequired && mb_strlen($password) < 8) || (!$passwordRequired && $password !== '' && mb_strlen($password) < 8)) $errors['password'] = 'Password minimal 8 karakter.';
        if ($errors !== []) throw new ValidationException($errors);
        return ['full_name' => $name, 'username' => $username, 'role' => $role, 'password_hash' => $password === '' ? null : password_hash($password, PASSWORD_DEFAULT)];
    }
    /** @param array<string,mixed> $parameters */
    private static function execute(PDOStatement $statement, array $parameters): void
    {
        try { $statement->execute($parameters); } catch (PDOException $exception) { if ($exception->getCode() === '23000') throw new ValidationException(['username' => 'Username sudah digunakan.']); throw $exception; }
    }
}
