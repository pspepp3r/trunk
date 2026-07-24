<?php

namespace Trunk\Console\Command;

use React\EventLoop\Loop;

class MigrateCommand extends MigrationCommand
{
    public static function description(): string
    {
        return 'Run all pending migrations';
    }

    public function handle(array $args): void
    {
        $this->migrator()->run()->then(
            Loop::get()->stop(...),
            function (\Throwable $e) {
                echo "Migration failed: " . $e->getMessage() . "\n";
                Loop::get()->stop();
            }
        );

        Loop::get()->run();
    }
}
