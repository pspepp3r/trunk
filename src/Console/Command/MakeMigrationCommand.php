<?php

namespace Trunk\Console\Command;

class MakeMigrationCommand extends Command
{
    public static function description(): string
    {
        return 'Create a new migration file';
    }

    public function handle(array $args): void
    {
        $name = $args[2] ?? null;

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
}
