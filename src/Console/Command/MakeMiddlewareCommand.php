<?php

namespace Trunk\Console\Command;

class MakeMiddlewareCommand extends Command
{
    public static function description(): string
    {
        return 'Create a new middleware skeleton file';
    }

    public function handle(array $args): void
    {
        $name = $args[2] ?? null;

        if (!$name) {
            echo "Error: Middleware name is required.\n";
            return;
        }

        $basePath = $this->app->getBasePath();
        $middlewareDir = "{$basePath}/src/Middleware";

        if (!is_dir($middlewareDir)) {
            mkdir($middlewareDir, 0755, true);
        }

        $filePath = "{$middlewareDir}/{$name}.php";
        if (file_exists($filePath)) {
            echo "Error: Middleware '{$name}' already exists.\n";
            return;
        }

        $template = <<<PHP
<?php

namespace App\Middleware;

use Trunk\Middleware\Interface\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

class {$name} implements MiddlewareInterface
{
    public function process(ServerRequestInterface \$request, callable \$next): PromiseInterface
    {
        // Perform action before request...

        return \$next(\$request)->then(function (\$response) {
            // Perform action after response...
            return \$response;
        });
    }
}
PHP;

        file_put_contents($filePath, $template);
        echo "Middleware '{$name}' created successfully at src/Middleware/{$name}.php\n";
    }
}
