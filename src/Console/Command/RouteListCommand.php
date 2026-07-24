<?php

namespace Trunk\Console\Command;

use ReflectionClass;
use Trunk\Http\Router;

class RouteListCommand extends Command
{
    public static function description(): string
    {
        return 'Display all registered API routes';
    }

    public function handle(array $args): void
    {
        $router = $this->app->getContainer()->get(Router::class);

        $reflector = new ReflectionClass($router);
        if ($reflector->hasProperty('routes')) {
            $property = $reflector->getProperty('routes');
            $routes = $property->getValue($router);

            echo str_pad('Method', 10) . ' | ' . "Path\n";
            echo str_repeat('-', 40) . "\n";
            foreach ($routes as $route) {
                echo str_pad($route['method'], 10) . ' | ' . $route['path'] . "\n";
            }
        } else {
            echo "Unable to read registered routes.\n";
        }
    }
}
