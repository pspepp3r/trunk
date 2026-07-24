<?php

namespace Trunk\Console\Command;

use React\EventLoop\Loop;

class MigrateStatusCommand extends MigrationCommand
{
    public static function description(): string
    {
        return 'Show which migrations have run';
    }

    public function handle(array $args): void
    {
        $this->migrator()->status()->then(
            Loop::get()->stop(...),
            function (\Throwable $e) {
                echo "Failed to read migration status: " . $e->getMessage() . "\n";
                Loop::get()->stop();
            }
        );

        Loop::get()->run();
    }
}
