<?php

declare(strict_types=1);

namespace Nex\Database;

use InvalidArgumentException;
use Nex\Contracts\Config\RepositoryInterface;
use Nex\Contracts\Container\ContainerInterface;
use Nex\Database\Contracts\ConnectionInterface;
use Nex\Database\Contracts\DatabaseManagerInterface;
use Nex\Database\Contracts\QueryBuilderInterface;
use PDO;
use Throwable;

class DatabaseManager implements DatabaseManagerInterface
{
    /**
     * @var array<string, ConnectionInterface>
     */
    private array $connections = [];

    public function __construct(
        private ?ContainerInterface $container = null,
        private ?RepositoryInterface $config = null
    ) {
    }

    public function connection(?string $name = null): ConnectionInterface
    {
        $name = $name ?? $this->getDefaultConnection();

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        return $this->connections[$name] = $this->makeConnection($name);
    }

    public function table(string $table, ?string $connection = null): QueryBuilderInterface
    {
        return $this->connection($connection)->table($table);
    }

    public function transaction(callable $callback, ?string $connection = null): mixed
    {
        $conn = $this->connection($connection);
        $conn->beginTransaction();

        try {
            $result = $callback($conn);
            $conn->commit();
            return $result;
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function disconnect(?string $name = null): void
    {
        $name = $name ?? $this->getDefaultConnection();
        unset($this->connections[$name]);
    }

    private function getDefaultConnection(): string
    {
        return (string) ($this->config?->get('database.default', 'sqlite') ?? 'sqlite');
    }

    private function makeConnection(string $name): ConnectionInterface
    {
        $config = (array) ($this->config?->get("database.connections.$name", []) ?? []);
        $driver = (string) ($config['driver'] ?? $name);

        $pdo = match ($driver) {
            'sqlite' => $this->createSqlitePdo($config),
            'mysql' => $this->createMysqlPdo($config),
            'pgsql' => $this->createPgsqlPdo($config),
            default => throw new InvalidArgumentException("Unsupported database driver: [$driver]"),
        };

        return new Connection($pdo, $driver);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createSqlitePdo(array $config): PDO
    {
        $database = (string) ($config['database'] ?? ':memory:');
        return new PDO("sqlite:$database");
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createMysqlPdo(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $db = $config['database'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
        return new PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createPgsqlPdo(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 5432;
        $db = $config['database'] ?? '';

        $dsn = "pgsql:host=$host;port=$port;dbname=$db";
        return new PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''));
    }
}
