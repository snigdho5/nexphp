<?php

declare(strict_types=1);

namespace Nex\Database\Migrations;

use Nex\Database\Contracts\ConnectionInterface;
use Nex\Database\Migrations\Schema\Blueprint;
use Nex\Database\Migrations\Schema\Schema;

class Migrator
{
    private Schema $schema;

    public function __construct(
        private ConnectionInterface $connection,
        private string $migrationTable = 'migrations'
    ) {
        $this->schema = new Schema($connection);
    }

    public function ensureMigrationTable(): void
    {
        $this->schema->create($this->migrationTable, function (Blueprint $table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });
    }

    /**
     * Run all pending migrations found in the given directory.
     *
     * @return list<string> Names of migrated files
     */
    public function run(string $migrationPath): array
    {
        $this->ensureMigrationTable();

        $ranMigrations = $this->getRan();
        $batch = $this->getNextBatchNumber();
        $files = $this->getMigrationFiles($migrationPath);

        $migrated = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');

            if (in_array($name, $ranMigrations, true)) {
                continue;
            }

            /** @var Migration $migration */
            $migration = require $file;
            $migration->up($this->schema);

            $this->connection->table($this->migrationTable)->insert([
                'migration' => $name,
                'batch' => $batch,
            ]);

            $migrated[] = $name;
        }

        return $migrated;
    }

    /**
     * Rollback the last migration batch.
     *
     * @return list<string> Names of rolled back files
     */
    public function rollback(string $migrationPath): array
    {
        $this->ensureMigrationTable();

        $lastBatch = $this->getLastBatchNumber();
        if ($lastBatch === 0) {
            return [];
        }

        $records = $this->connection->table($this->migrationTable)
            ->where('batch', $lastBatch)
            ->orderBy('id', 'desc')
            ->get();

        $rolledBack = [];

        foreach ($records as $record) {
            $name = $record['migration'];
            $file = rtrim($migrationPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . '.php';

            if (file_exists($file)) {
                /** @var Migration $migration */
                $migration = require $file;
                $migration->down($this->schema);
            }

            $this->connection->table($this->migrationTable)
                ->where('migration', $name)
                ->delete();

            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * @return list<string>
     */
    public function getRan(): array
    {
        $records = $this->connection->table($this->migrationTable)->get();
        return array_column($records, 'migration');
    }

    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    public function getLastBatchNumber(): int
    {
        $record = $this->connection->table($this->migrationTable)
            ->orderBy('batch', 'desc')
            ->first();

        return (int) ($record['batch'] ?? 0);
    }

    /**
     * @return list<string>
     */
    private function getMigrationFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        return $files;
    }
}
