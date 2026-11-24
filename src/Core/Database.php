<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private function __construct(private ?PDO $connection, private ?string $connectionError = null)
    {
    }

    public static function fromEnvironment(): self
    {
        $host = getenv('DB_HOST') ?: '';
        $database = getenv('DB_DATABASE') ?: '';
        $username = getenv('DB_USERNAME') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';
        if ($host === '' || $database === '' || $username === '') {
            return new self(null, 'Database settings are not complete. Copy .env.example to .env and enter the connection details.');
        }

        $port = getenv('DB_PORT') ?: '3306';
        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            return new self($pdo);
        } catch (PDOException $exception) {
            return new self(null, 'Could not connect to the database. Check the .env connection settings.');
        }
    }

    public function isAvailable(): bool
    {
        return $this->connection instanceof PDO;
    }

    public function errorMessage(): ?string
    {
        return $this->connectionError;
    }

    public function count(string $table): int
    {
        if (!$this->connection || !in_array($table, ['teachers', 'teaching_groups', 'rooms', 'scheduled_sessions'], true)) {
            return 0;
        }

        return (int) $this->connection->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
}

