<?php

namespace Trunk\Console\Command;

use Trunk\Console\Command\Interface\CommandInterface;

class HelpCommand implements CommandInterface
{
    /**
     * @param array<string, class-string<CommandInterface>> $commands
     */
    public function __construct(private readonly array $commands)
    {
    }

    public static function description(): string
    {
        return 'Display this help message';
    }

    public function handle(array $args): void
    {
        echo "Trunk Async Framework Console\n";
        echo "Usage: php trunk <command> [options]\n\n";
        echo "Available Commands:\n";

        foreach ($this->commands as $name => $class) {
            echo str_pad($name, 20) . $class::description() . "\n";
        }
    }
}
