<?php

declare(strict_types=1);

namespace Nex\Queue;

use Nex\Database\Contracts\ConnectionInterface;
use Nex\Queue\Contracts\QueueInterface;
use Nex\Queue\Contracts\QueuedJobInterface;
use RuntimeException;

/**
 * Database-backed queue adapter.
 *
 * Table schema (create with the queue:migrate command or migration):
 *   CREATE TABLE nex_jobs (
 *       id           TEXT NOT NULL PRIMARY KEY,
 *       queue        TEXT NOT NULL DEFAULT 'default',
 *       payload      TEXT NOT NULL,
 *       attempts     INTEGER NOT NULL DEFAULT 0,
 *       max_attempts INTEGER NOT NULL DEFAULT 3,
 *       available_at INTEGER NOT NULL,
 *       reserved_at  INTEGER,
 *       created_at   INTEGER NOT NULL
 *   );
 *   CREATE TABLE nex_failed_jobs (
 *       id           TEXT NOT NULL PRIMARY KEY,
 *       queue        TEXT NOT NULL,
 *       payload      TEXT NOT NULL,
 *       exception    TEXT NOT NULL,
 *       failed_at    INTEGER NOT NULL
 *   );
 *
 * Reliability model:
 * - pop() atomically reserves a row (sets reserved_at) using a CAS-style UPDATE.
 * - Stale reservations (> $visibilityTimeout seconds) are automatically re-queued by the next pop() call.
 * - acknowledge() deletes the row on success.
 * - fail() moves the row to nex_failed_jobs.
 */
final class DatabaseQueue implements QueueInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $defaultQueue = 'default',
        private readonly int $visibilityTimeout = 60,
    ) {}

    /** Ensure the queue tables exist (idempotent). */
    public function createTables(): void
    {
        $this->connection->statement(<<<SQL
            CREATE TABLE IF NOT EXISTS nex_jobs (
                id           TEXT NOT NULL PRIMARY KEY,
                queue        TEXT NOT NULL DEFAULT 'default',
                payload      TEXT NOT NULL,
                attempts     INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 3,
                available_at INTEGER NOT NULL,
                reserved_at  INTEGER,
                created_at   INTEGER NOT NULL
            )
        SQL);

        $this->connection->statement(<<<SQL
            CREATE TABLE IF NOT EXISTS nex_failed_jobs (
                id        TEXT NOT NULL PRIMARY KEY,
                queue     TEXT NOT NULL,
                payload   TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at INTEGER NOT NULL
            )
        SQL);
    }

    public function push(object $job, array $options = []): string
    {
        $queue = $options['queue'] ?? $this->defaultQueue;
        $delay = (int) ($options['delay'] ?? 0);
        $maxAttempts = (int) ($options['attempts'] ?? 3);

        // Serialize: only class name + constructor properties (no live resources)
        $payload = $this->serialize($job);
        $id = $this->generateId();
        $now = time();

        $this->connection->statement(
            'INSERT INTO nex_jobs (id, queue, payload, attempts, max_attempts, available_at, created_at) VALUES (?, ?, ?, 0, ?, ?, ?)',
            [$id, $queue, $payload, $maxAttempts, $now + $delay, $now],
        );

        return $id;
    }

    public function pop(?string $queue = null): ?QueuedJobInterface
    {
        $queue = $queue ?? $this->defaultQueue;
        $now = time();
        $staleThreshold = $now - $this->visibilityTimeout;

        // Re-queue stale reservations first (crash recovery)
        $this->connection->statement(
            'UPDATE nex_jobs SET reserved_at = NULL WHERE queue = ? AND reserved_at IS NOT NULL AND reserved_at < ?',
            [$queue, $staleThreshold],
        );

        // Find the next available job
        $rows = $this->connection->select(
            'SELECT * FROM nex_jobs WHERE queue = ? AND reserved_at IS NULL AND available_at <= ? ORDER BY available_at ASC LIMIT 1',
            [$queue, $now],
        );

        if (empty($rows)) {
            return null;
        }

        $row = $rows[0];
        $newAttempts = (int) $row['attempts'] + 1;

        // Atomically reserve it
        $updated = $this->connection->statement(
            'UPDATE nex_jobs SET reserved_at = ?, attempts = ? WHERE id = ? AND reserved_at IS NULL',
            [$now, $newAttempts, $row['id']],
        );

        if (!$updated) {
            // Another worker grabbed it — try again (recursive, single hop)
            return $this->pop($queue);
        }

        return new QueuedJob(
            id: (string) $row['id'],
            queue: (string) $row['queue'],
            attempts: $newAttempts,
            payload: json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR),
            reservedAt: $now,
            maxAttempts: (int) $row['max_attempts'],
        );
    }

    public function acknowledge(QueuedJobInterface $job): void
    {
        $this->connection->statement('DELETE FROM nex_jobs WHERE id = ?', [$job->getId()]);
    }

    public function release(QueuedJobInterface $job, int $delaySeconds = 0): void
    {
        $this->connection->statement(
            'UPDATE nex_jobs SET reserved_at = NULL, available_at = ? WHERE id = ?',
            [time() + $delaySeconds, $job->getId()],
        );
    }

    public function fail(QueuedJobInterface $job, string $exceptionMessage): void
    {
        $this->connection->statement('DELETE FROM nex_jobs WHERE id = ?', [$job->getId()]);
        $this->connection->statement(
            'INSERT INTO nex_failed_jobs (id, queue, payload, exception, failed_at) VALUES (?, ?, ?, ?, ?)',
            [$job->getId(), $job->getQueue(), json_encode($job->getPayload(), JSON_THROW_ON_ERROR), $exceptionMessage, time()],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getFailedJobs(): array
    {
        return $this->connection->select('SELECT * FROM nex_failed_jobs ORDER BY failed_at DESC');
    }

    public function deleteFailedJob(string $id): void
    {
        $this->connection->statement('DELETE FROM nex_failed_jobs WHERE id = ?', [$id]);
    }

    public function retryFailedJob(string $id, array $options = []): void
    {
        $rows = $this->connection->select('SELECT * FROM nex_failed_jobs WHERE id = ?', [$id]);
        if (empty($rows)) {
            throw new RuntimeException("Failed job [$id] not found.");
        }

        $row = $rows[0];
        $newId = $this->generateId();
        $now = time();
        $queue = $options['queue'] ?? (string) $row['queue'];
        $maxAttempts = (int) ($options['attempts'] ?? 3);

        $this->connection->statement(
            'INSERT INTO nex_jobs (id, queue, payload, attempts, max_attempts, available_at, created_at) VALUES (?, ?, ?, 0, ?, ?, ?)',
            [$newId, $queue, $row['payload'], $maxAttempts, $now, $now],
        );

        $this->connection->statement('DELETE FROM nex_failed_jobs WHERE id = ?', [$id]);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function serialize(object $job): string
    {
        // Safe serialization: only public + readonly properties (no closures/resources)
        $ref = new \ReflectionClass($job);
        $data = [];

        foreach ($ref->getProperties() as $prop) {
            if ($prop->isStatic()) {
                continue;
            }
            $value = $prop->getValue($job);

            // Safety: refuse closures and resources
            if ($value instanceof \Closure) {
                throw new \InvalidArgumentException(
                    "Job [" . $job::class . "] property [\${$prop->getName()}] is a Closure. " .
                    "Jobs must only contain serializable values (scalars, arrays, DTOs)."
                );
            }
            if (is_resource($value)) {
                throw new \InvalidArgumentException(
                    "Job [" . $job::class . "] property [\${$prop->getName()}] is a resource and cannot be queued."
                );
            }

            $data[$prop->getName()] = $value;
        }

        return json_encode([
            'job'     => $job::class,
            'version' => 1,
            'data'    => $data,
        ], JSON_THROW_ON_ERROR);
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
