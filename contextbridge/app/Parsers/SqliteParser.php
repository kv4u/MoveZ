<?php
declare(strict_types=1);

namespace App\Parsers;

use PDO;
use PDOException;
use RuntimeException;

abstract class SqliteParser
{
    protected function openDb(string $path): PDO
    {
        if (!file_exists($path)) {
            throw new RuntimeException("SQLite database not found: {$path}");
        }

        try {
            $pdo = new PDO("sqlite:{$path}");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException("Cannot open SQLite database: {$e->getMessage()}", 0, $e);
        }
    }

    protected function queryKey(PDO $db, string $key): ?string
    {
        $stmt = $db->prepare('SELECT value FROM ItemTable WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? (string) $row['value'] : null;
    }
}
