<?php

namespace Trunk\Console\Command;

class StartCommand extends Command
{
    public static function description(): string
    {
        return 'Start the ReactPHP async API server';
    }

    public function handle(array $args): void
    {
        $port = config('app.port', '8080');
        $this->app->run("127.0.0.1:{$port}");
    }
}
