<?php

namespace Trunk\Database;

use React\Promise\PromiseInterface;
use Trunk\Database\Schema\Blueprint;
use Trunk\Database\Schema\SchemaBuilder;

use function count;
use function in_array;
use function printf;
use function React\Promise\resolve;

class Migrator
{
    public function __construct(
        private readonly Connection $db,
        private readonly SchemaBuilder $schema,
        private readonly string $migrationsPath,
    ) {}

    public function run(): PromiseInterface
    {
        return $this->ensureMigrationsTable()
            ->then(fn() => $this->db->query('SELECT migration FROM migrations'))
            ->then(function ($result) {
                $ran = array_column($result->rows, 'migration');
                $pending = array_values(array_filter(
                    $this->migrationFiles(),
                    fn(string $file) => !in_array($this->nameFromFile($file), $ran, true)
                ));

                if (empty($pending)) {
                    echo "Nothing to migrate.\n";
                    return resolve(null);
                }

                return $this->db->query('SELECT MAX(batch) as max_batch FROM migrations')
                    ->then(function ($batchResult) use ($pending) {
                        $batch = ((int) ($batchResult->rows[0]['max_batch'] ?? 0)) + 1;
                        return $this->runPending($pending, $batch);
                    });
            });
    }

    public function rollback(int $steps = 1): PromiseInterface
    {
        return $this->ensureMigrationsTable()
            ->then(fn() => $this->db->query('SELECT DISTINCT batch FROM migrations ORDER BY batch DESC LIMIT ?', [$steps]))
            ->then(function ($result) {
                $batches = array_column($result->rows, 'batch');

                if (empty($batches)) {
                    echo "Nothing to rollback.\n";
                    return resolve(null);
                }

                $placeholders = implode(', ', array_fill(0, count($batches), '?'));

                return $this->db->query(
                    "SELECT migration, batch FROM migrations WHERE batch IN ({$placeholders}) ORDER BY id DESC",
                    $batches
                )->then(fn($rowsResult) => $this->rollbackRows($rowsResult->rows));
            });
    }

    public function status(): PromiseInterface
    {
        return $this->ensureMigrationsTable()
            ->then(fn() => $this->db->query('SELECT migration, batch FROM migrations'))
            ->then(function ($result) {
                $ran = [];
                foreach ($result->rows as $row) {
                    $ran[$row['migration']] = $row['batch'];
                }

                foreach ($this->migrationFiles() as $file) {
                    $name = $this->nameFromFile($file);
                    $status = isset($ran[$name]) ? "Ran (batch {$ran[$name]})" : 'Pending';
                    printf("%-60s %s\n", $name, $status);
                }
            });
    }

    private function ensureMigrationsTable(): PromiseInterface
    {
        return $this->schema->create('migrations', function (Blueprint $table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });
    }

    private function runPending(array $files, int $batch): PromiseInterface
    {
        $promise = resolve(null);

        foreach ($files as $file) {
            $promise = $promise->then(function () use ($file, $batch) {
                $name = $this->nameFromFile($file);
                $migration = $this->loadMigration($file);

                echo "Migrating:  {$name}\n";

                return $migration->up($this->schema)
                    ->then(fn() => $this->db->query(
                        'INSERT INTO migrations (migration, batch) VALUES (?, ?)',
                        [$name, $batch]
                    ))
                    ->then(function () use ($name) {
                        echo "Migrated:   {$name}\n";
                    });
            });
        }

        return $promise;
    }

    private function rollbackRows(array $rows): PromiseInterface
    {
        $promise = resolve(null);

        foreach ($rows as $row) {
            $promise = $promise->then(function () use ($row) {
                $name = $row['migration'];
                $file = "{$this->migrationsPath}/$name.php";

                if (!file_exists($file)) {
                    echo "Skipping {$name}: migration file no longer exists.\n";
                    return $this->db->query('DELETE FROM migrations WHERE migration = ?', [$name]);
                }

                $migration = $this->loadMigration($file);
                echo "Rolling back: {$name}\n";

                return $migration->down($this->schema)
                    ->then(fn() => $this->db->query('DELETE FROM migrations WHERE migration = ?', [$name]))
                    ->then(function () use ($name) {
                        echo "Rolled back:  {$name}\n";
                    });
            });
        }

        return $promise;
    }

    private function migrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob("{$this->migrationsPath}/*.php") ?: [];
        sort($files);

        return $files;
    }

    private function nameFromFile(string $file): string
    {
        return pathinfo($file, PATHINFO_FILENAME);
    }

    private function loadMigration(string $file): Migration
    {
        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new \RuntimeException("Migration file '{$file}' must return an instance of Trunk\\Database\\Migration.");
        }

        return $migration;
    }
}
