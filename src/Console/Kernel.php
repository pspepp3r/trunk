<?php

namespace Trunk\Console;

use Trunk\App;
use Trunk\Console\Command\DbCreateCommand;
use Trunk\Console\Command\HelpCommand;
use Trunk\Console\Command\KeyGenerateCommand;
use Trunk\Console\Command\MakeControllerCommand;
use Trunk\Console\Command\MakeMiddlewareCommand;
use Trunk\Console\Command\MakeMigrationCommand;
use Trunk\Console\Command\MigrateCommand;
use Trunk\Console\Command\MigrateRollbackCommand;
use Trunk\Console\Command\MigrateStatusCommand;
use Trunk\Console\Command\RouteListCommand;
use Trunk\Console\Command\SchemaDiffCommand;
use Trunk\Console\Command\StartCommand;

class Kernel
{
    /** @var array<string, class-string<\Trunk\Console\Command\Interface\CommandInterface>> */
    private array $commands;

    public function __construct(private readonly App $app)
    {
        $this->commands = [
            'start' => StartCommand::class,
            'route:list' => RouteListCommand::class,
            'make:controller' => MakeControllerCommand::class,
            'make:middleware' => MakeMiddlewareCommand::class,
            'orm:schema-diff' => SchemaDiffCommand::class,
            'make:migration' => MakeMigrationCommand::class,
            'db:create' => DbCreateCommand::class,
            'migrate' => MigrateCommand::class,
            'migrate:rollback' => MigrateRollbackCommand::class,
            'migrate:status' => MigrateStatusCommand::class,
            'key:generate' => KeyGenerateCommand::class,
        ];
    }

    public function handle(array $args): void
    {
        $name = $args[1] ?? 'help';

        if ($name === 'help' || !isset($this->commands[$name])) {
            (new HelpCommand($this->commands))->handle($args);
            return;
        }

        $command = $this->app->getContainer()->get($this->commands[$name]);
        $command->handle($args);
    }
}
