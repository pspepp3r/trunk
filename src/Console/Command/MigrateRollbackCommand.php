<?php

namespace Trunk\Console\Command;

use React\EventLoop\Loop;

class MigrateRollbackCommand extends MigrationCommand
{
    public static function description(): string
    {
        return 'Roll back the last migration batch (--step=N for more)';
    }

    public function handle(array $args): void
    {
        $steps = $this->parseStepOption($args);

        $this->migrator()->rollback($steps)->then(function () {
            Loop::get()->stop();
        }, function (\Throwable $e) {
            echo "Rollback failed: " . $e->getMessage() . "\n";
            Loop::get()->stop();
        });

        Loop::get()->run();
    }

    private function parseStepOption(array $args): int
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--step=')) {
                return max(1, (int) substr($arg, 7));
            }
        }

        return 1;
    }
}
