<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = Database::connection();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(190) NOT NULL PRIMARY KEY,
        executed_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$executed = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(ROOT_PATH . '/database/migrations/*.sql') ?: [];
sort($files, SORT_NATURAL);

foreach ($files as $file) {
    $migration = basename($file);
    if (in_array($migration, $executed, true)) {
        echo "Skip {$migration}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Tidak dapat membaca {$migration}");
    }

    try {
        $statements = preg_split('/;\s*(?:\R|$)/', $sql) ?: [];
        foreach ($statements as $migrationSql) {
            $migrationSql = trim($migrationSql);
            if ($migrationSql !== '') {
                $pdo->exec($migrationSql);
            }
        }
        $statement = $pdo->prepare('INSERT INTO schema_migrations (migration, executed_at) VALUES (:migration, UTC_TIMESTAMP())');
        $statement->execute(['migration' => $migration]);
        echo "Applied {$migration}\n";
    } catch (Throwable $exception) {
        fwrite(STDERR, "Migration failed: {$migration}\n");
        throw $exception;
    }
}
