<?php

namespace Trunk\Console\Command;

class MakeControllerCommand extends Command
{
    public static function description(): string
    {
        return 'Create a new controller skeleton file';
    }

    public function handle(array $args): void
    {
        $name = $args[2] ?? null;

        if (!$name) {
            echo "Error: Controller name is required.\n";
            return;
        }

        $basePath = $this->app->getBasePath();
        $controllerDir = "{$basePath}/src/Controllers";

        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0755, true);
        }

        $filePath = "{$controllerDir}/{$name}.php";
        if (file_exists($filePath)) {
            echo "Error: Controller '{$name}' already exists.\n";
            return;
        }

        $template = <<<PHP
<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Trunk\Http\Response;
use React\Http\Message\Response as ReactResponse;

class {$name}
{
    public function index(ServerRequestInterface \$request): ReactResponse
    {
        return Response::json(['message' => 'Hello from {$name}!']);
    }
}
PHP;

        file_put_contents($filePath, $template);
        echo "Controller '{$name}' created successfully at src/Controllers/{$name}.php\n";
    }
}
