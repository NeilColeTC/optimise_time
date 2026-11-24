<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

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
        if (!$this->connection || !in_array($table, ['teachers', 'courses', 'rooms', 'scheduled_sessions'], true)) {
            return 0;
        }

        return (int) $this->connection->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function all(string $sql, array $parameters = []): array
    {
        return $this->statement($sql, $parameters)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function one(string $sql, array $parameters = []): ?array
    {
        $row = $this->statement($sql, $parameters)->fetch();
        return $row === false ? null : $row;
    }

    public function execute(string $sql, array $parameters = []): int
    {
        return $this->statement($sql, $parameters)->rowCount();
    }

    public function insertId(): int
    {
        return (int) $this->connection()->lastInsertId();
    }

    public function transaction(callable $callback): mixed
    {
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $result = $callback($this);
            $connection->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    private function statement(string $sql, array $parameters): \PDOStatement
    {
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement;
    }

    private function connection(): PDO
    {
        if (!$this->connection) {
            throw new RuntimeException($this->connectionError ?? 'Database connection is unavailable.');
        }
        return $this->connection;
    }
}
