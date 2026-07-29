<?php

namespace Trunk\Console\Command;

use React\EventLoop\Loop;
use ReflectionClass;
use Trunk\Database\Connection;
use Trunk\Database\ORM\Attributes\Column;
use Trunk\Database\ORM\Attributes\Entity;

class SchemaDiffCommand extends Command
{
    public static function description(): string
    {
        return 'Diff entities against database schema and generate a migration file';
    }

    public function handle(array $args): void
    {
        $this->app->bootProviders();

        $basePath = $this->app->getBasePath();
        // Assuming user entities are in src/Entities or src/App/Entities. We'll check src/
        $srcDir = "{$basePath}/src";

        if (!is_dir($srcDir)) {
            echo "No src directory found.\n";
            return;
        }

        $db = $this->app->getContainer()->get(Connection::class);
        $grammar = $db->grammar();
        
        $entities = $this->findEntities($srcDir);
        if (empty($entities)) {
            echo "No classes with #[Entity] attribute found.\n";
            return;
        }

        $queries = [];
        
        // Simplified diff: For now, we just generate CREATE TABLE for all entities 
        // as a prototype. A full Doctrine-style SchemaManager would inspect information_schema.
        foreach ($entities as $class) {
            $reflector = new ReflectionClass($class);
            
            $entityAttr = $reflector->getAttributes(Entity::class)[0]->newInstance();
            $tableName = $entityAttr->table ?? strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $reflector->getShortName())) . 's';

            $columns = [];
            foreach ($reflector->getProperties() as $property) {
                $attributes = $property->getAttributes(Column::class);
                if (empty($attributes)) {
                    continue;
                }

                $columnAttr = $attributes[0]->newInstance();
                
                $name = $columnAttr->name ?? strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $property->getName()));
                
                if ($columnAttr->primary || $name === 'id') {
                    $columns[] = $grammar->primaryKeyColumn($name);
                    continue;
                }

                $type = $columnAttr->type ?? 'VARCHAR';
                $length = $columnAttr->length ? "({$columnAttr->length})" : ($type === 'VARCHAR' ? '(255)' : '');
                $null = $columnAttr->nullable ? 'NULL' : 'NOT NULL';
                
                $columns[] = sprintf('%s %s%s %s', $grammar->quoteIdentifier($name), $type, $length, $null);
            }

            if (empty($columns)) {
                continue;
            }

            // We generate a "CREATE TABLE IF NOT EXISTS" for simplicity in this prototype.
            $sql = sprintf(
                "CREATE TABLE IF NOT EXISTS %s (\n  %s\n)%s;",
                $grammar->quoteIdentifier($tableName),
                implode(",\n  ", $columns),
                $grammar->tableOptions()
            );

            $queries[] = $sql;
        }

        if (empty($queries)) {
            echo "No schema changes required.\n";
            return;
        }

        $this->generateMigrationFile($basePath, $queries);
    }

    private function findEntities(string $dir): array
    {
        $entities = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                // Quick check to avoid loading all files
                if (str_contains($content, '#[Entity]')) {
                    // Extract namespace and classname
                    if (preg_match('/namespace\s+([^;]+);/i', $content, $nsMatch) && 
                        preg_match('/class\s+([a-zA-Z0-9_]+)/i', $content, $classMatch)) {
                        $className = trim($nsMatch[1]) . '\\' . trim($classMatch[1]);
                        if (!class_exists($className)) {
                            require_once $file->getPathname();
                        }
                        if (class_exists($className) && !empty((new ReflectionClass($className))->getAttributes(Entity::class))) {
                            $entities[] = $className;
                        }
                    }
                }
            }
        }
        return $entities;
    }

    private function generateMigrationFile(string $basePath, array $queries): void
    {
        $migrationsDir = $basePath . '/database/migrations';
        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0777, true);
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_schema_diff.php";
        $filepath = "{$migrationsDir}/{$filename}";

        $upSql = implode("\n\n        ", array_map(fn($q) => "\$db->query(\"" . addslashes($q) . "\");", $queries));

        $content = <<<PHP
<?php

use Trunk\Database\Migration;
use Trunk\Database\Connection;
use React\Promise\PromiseInterface;

return new class extends Migration
{
    public function up(Connection \$db): PromiseInterface
    {
        // Generated by schema diff
        {$upSql}
        
        return \React\Promise\resolve();
    }

    public function down(Connection \$db): PromiseInterface
    {
        // TODO: reverse diff
        return \React\Promise\resolve();
    }
};
PHP;

        file_put_contents($filepath, $content);
        echo "Migration file generated: database/migrations/{$filename}\n";
    }
}
