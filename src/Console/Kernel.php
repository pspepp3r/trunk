<?php

namespace Trunk\Console;

use React\EventLoop\Loop;
use Trunk\App;
use Trunk\Database\Connection;
use Trunk\Database\Schema\SchemaBuilder;
use Trunk\Http\Router;

use function sprintf;

class Kernel
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function handle(array $args): void
    {
        $command = $args[1] ?? 'help';

        switch ($command) {
            case 'start':
                $port = config('app.port', '8080');
                $this->app->run("127.0.0.1:{$port}");
                break;

            case 'route:list':
                $this->listRoutes();
                break;

            case 'make:controller':
                $this->makeController($args[2] ?? null);
                break;

            case 'make:middleware':
                $this->makeMiddleware($args[2] ?? null);
                break;

            case 'schema:sync':
                $this->syncSchema();
                break;

            case 'make:migration':
                $this->makeMigration($args[2] ?? null);
                break;

            case 'migrate':
                $this->migrate();
                break;

            case 'migrate:rollback':
                $this->migrateRollback($this->parseStepOption($args));
                break;

            case 'migrate:status':
                $this->migrateStatus();
                break;

            case 'help':
            default:
                echo "Trunk Async Framework Console\n";
                echo "Usage: php trunk <command> [options]\n\n";
                echo "Available Commands:\n";
                echo "  start               Start the ReactPHP async API server\n";
                echo "  route:list          Display all registered API routes\n";
                echo "  make:controller     Create a new controller skeleton file\n";
                echo "  make:middleware     Create a new middleware skeleton file\n";
                echo "  schema:sync         Analyze entities and synchronize database tables (quick prototyping)\n";
                echo "  make:migration      Create a new migration file\n";
                echo "  migrate             Run all pending migrations\n";
                echo "  migrate:rollback    Roll back the last migration batch (--step=N for more)\n";
                echo "  migrate:status      Show which migrations have run\n";
                break;
        }
    }

    private function listRoutes(): void
    {
        $router = $this->app->getContainer()->get(Router::class);

        // We'll use Reflection to fetch private routes property of Router
        $reflector = new \ReflectionClass($router);
        if ($reflector->hasProperty('routes')) {
            $property = $reflector->getProperty('routes');
            $routes = $property->getValue($router);

            echo str_pad("Method", 10) . " | " . "Path\n";
            echo str_repeat("-", 40) . "\n";
            foreach ($routes as $route) {
                echo str_pad($route['method'], 10) . " | " . $route['path'] . "\n";
            }
        } else {
            echo "Unable to read registered routes.\n";
        }
    }

    private function makeController(?string $name): void
    {
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

    private function makeMiddleware(?string $name): void
    {
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

    private function syncSchema(): void
    {
        // 1. Boot Service Providers to resolve database configurations
        $this->app->bootProviders();

        $basePath = $this->app->getBasePath();
        $entitiesDir = "{$basePath}/src/Entities";

        if (!is_dir($entitiesDir)) {
            echo "No Entities folder found. Nothing to sync.\n";
            return;
        }

        $db = $this->app->getContainer()->get(Connection::class);
        $grammar = $db->grammar();

        $entityFiles = scandir($entitiesDir);
        $queries = [];

        foreach ($entityFiles as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            $className = 'App\\Entities\\' . pathinfo($file, PATHINFO_FILENAME);
            if (!class_exists($className)) {
                require_once "{$entitiesDir}/$file";
            }

            $reflector = new \ReflectionClass($className);
            $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $reflector->getShortName())) . 's';

            $columns = [];
            foreach ($reflector->getProperties() as $property) {
                $name = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $property->getName()));

                if ($name === 'id') {
                    $columns[] = $grammar->primaryKeyColumn($name);
                    continue;
                }

                $type = 'VARCHAR(255)';
                if ($property->hasType()) {
                    $propType = $property->getType();
                    if ($propType instanceof \ReflectionNamedType) {
                        $type = $grammar->columnType($propType->getName());
                    }
                }

                $columns[] = sprintf('%s %s NULL', $grammar->quoteIdentifier($name), $type);
            }

            $sql = sprintf(
                "CREATE TABLE IF NOT EXISTS %s (\n  %s\n)%s;",
                $grammar->quoteIdentifier($tableName),
                implode(",\n  ", $columns),
                $grammar->tableOptions()
            );

            $queries[$tableName] = $sql;
        }

        if (empty($queries)) {
            echo "No entities found to synchronize.\n";
            return;
        }

        $promises = [];

        foreach ($queries as $table => $sql) {
            echo "Synchronizing table '{$table}'...\n";
            $promises[] = $db->query($sql)->then(
                function () use ($table) {
                    echo "Table '{$table}' synced successfully.\n";
                },
                function (\Throwable $e) use ($table) {
                    echo "Failed to sync table '{$table}': " . $e->getMessage() . "\n";
                }
            );
        }

        // Start the ReactPHP loop to run the async database connection queries
        \React\Promise\all($promises)->then(function () {
            echo "Schema synchronization completed.\n";
            Loop::get()->stop();
        });

        Loop::get()->run();
    }

    private function makeMigration(?string $name): void
    {
        if (!$name) {
            echo "Error: Migration name is required.\n";
            return;
        }

        $basePath = $this->app->getBasePath();
        $migrationsDir = "{$basePath}/database/migrations";

        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0755, true);
        }

        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        $fileName = date('Y_m_d_His') . '_' . $snake . '.php';
        $filePath = "{$migrationsDir}/$fileName";
        $table = $this->guessTableName($snake);

        $template = <<<PHP
<?php

use React\Promise\PromiseInterface;
use Trunk\Database\Migration;
use Trunk\Database\Schema\Blueprint;
use Trunk\Database\Schema\SchemaBuilder;

return new class extends Migration {
    public function up(SchemaBuilder \$schema): PromiseInterface
    {
        return \$schema->create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(SchemaBuilder \$schema): PromiseInterface
    {
        return \$schema->drop('{$table}');
    }
};
PHP;

        file_put_contents($filePath, $template);
        echo "Migration created successfully at database/migrations/{$fileName}\n";
    }

    private function guessTableName(string $snake): string
    {
        if (preg_match('/^create_(.+)_table$/', $snake, $matches)) {
            return $matches[1];
        }

        return $snake;
    }

    private function migrator(): \Trunk\Database\Migrator
    {
        $this->app->bootProviders();

        $db = $this->app->getContainer()->get(Connection::class);
        $schema = new SchemaBuilder($db);
        $migrationsPath = $this->app->getBasePath() . '/database/migrations';

        return new \Trunk\Database\Migrator($db, $schema, $migrationsPath);
    }

    private function migrate(): void
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

    private function migrateRollback(int $steps): void
    {
        $this->migrator()->rollback($steps)->then(function () {
            Loop::get()->stop();
        }, function (\Throwable $e) {
            echo "Rollback failed: " . $e->getMessage() . "\n";
            Loop::get()->stop();
        });

        Loop::get()->run();
    }

    private function migrateStatus(): void
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
