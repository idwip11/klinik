<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

function prompt(string $label): string
{
    $value = readline($label . ': ');
    return trim((string) $value);
}

$fullName = prompt('Nama lengkap administrator');
$username = prompt('Username');
$password = prompt('Password (minimal 12 karakter)');

if ($fullName === '' || $username === '' || mb_strlen($username) > 64 || mb_strlen($password) < 12) {
    fwrite(STDERR, "Input tidak valid. Username wajib diisi; password minimal 12 karakter.\n");
    exit(1);
}

$statement = Database::connection()->prepare(
    'INSERT INTO users (full_name, username, password_hash, role, is_active, created_at, updated_at)
     VALUES (:full_name, :username, :password_hash, :role, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
);

try {
    $statement->execute([
        'full_name' => $fullName,
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'admin',
    ]);
    echo "Administrator berhasil dibuat.\n";
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        fwrite(STDERR, "Username sudah digunakan.\n");
        exit(1);
    }
    throw $exception;
}
