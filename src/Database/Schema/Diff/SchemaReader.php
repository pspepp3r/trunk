<?php

namespace Trunk\Database\Schema\Diff;

use ReflectionClass;
use ReflectionProperty;
use Trunk\Database\ORM\Attributes\Column;
use Trunk\Database\ORM\Attributes\Entity;
use Trunk\Database\ORM\Attributes\ManyToMany;
use Trunk\Database\ORM\Attributes\ManyToOne;
use Trunk\Database\ORM\Attributes\OneToOne;

/** Builds the "expected" schema from #[Entity]/#[Column]/relationship attributes - see the Database guide's Relationships section. */
class SchemaReader
{
    /** @var array<class-string, string> */
    private array $tableNames = [];

    /**
     * @param class-string[] $entityClasses
     * @return array<string, TableSchema> Keyed by table name.
     */
    public function read(array $entityClasses): array
    {
        foreach ($entityClasses as $class) {
            $this->tableNames[$class] = $this->resolveTableName($class);
        }

        $tables = [];

        foreach ($entityClasses as $class) {
            $reflector = new ReflectionClass($class);
            $tableName = $this->tableNames[$class];

            $columns = [];
            $foreignKeys = [];

            foreach ($reflector->getProperties() as $property) {
                $columnAttrs = $property->getAttributes(Column::class);
                if (!empty($columnAttrs)) {
                    $column = $this->readColumn($property, $columnAttrs[0]->newInstance());
                    $columns[$column->name] = $column;
                    continue;
                }

                $foreignKey = $this->readOwningRelation($property);
                if ($foreignKey !== null) {
                    $columns[$foreignKey->column] = new ColumnSchema($foreignKey->column, 'integer', nullable: true);
                    $foreignKeys[$foreignKey->column] = $foreignKey;
                    continue;
                }

                $pivot = $this->readManyToMany($property, $tableName);
                if ($pivot !== null) {
                    $tables[$pivot->name] = $pivot;
                }
            }

            if (!empty($columns)) {
                $existing = $tables[$tableName] ?? null;
                $tables[$tableName] = new TableSchema(
                    $tableName,
                    $columns + ($existing?->columns ?? []),
                    $foreignKeys + ($existing?->foreignKeys ?? [])
                );
            }
        }

        return $tables;
    }

    private function readColumn(ReflectionProperty $property, Column $attr): ColumnSchema
    {
        $name = $attr->name ?? $this->snakeCase($property->getName());
        $isPrimary = $attr->primary || $name === 'id';

        return new ColumnSchema(
            name: $name,
            type: $attr->type ?? 'VARCHAR',
            length: $attr->length,
            nullable: $attr->nullable,
            primary: $isPrimary,
            autoIncrement: $isPrimary,
        );
    }

    private function readOwningRelation(ReflectionProperty $property): ?ForeignKeySchema
    {
        $manyToOne = $property->getAttributes(ManyToOne::class);
        if (!empty($manyToOne)) {
            /** @var ManyToOne $attr */
            $attr = $manyToOne[0]->newInstance();
            return $this->foreignKeyFor($property, $attr->targetEntity);
        }

        $oneToOne = $property->getAttributes(OneToOne::class);
        if (!empty($oneToOne)) {
            /** @var OneToOne $attr */
            $attr = $oneToOne[0]->newInstance();
            if ($attr->mappedBy !== null) {
                return null; // inverse side - the owning side carries the column
            }
            return $this->foreignKeyFor($property, $attr->targetEntity);
        }

        return null;
    }

    private function foreignKeyFor(ReflectionProperty $property, ?string $targetEntity): ?ForeignKeySchema
    {
        if ($targetEntity === null || !isset($this->tableNames[$targetEntity])) {
            return null;
        }

        $column = $this->snakeCase($property->getName()) . '_id';

        return new ForeignKeySchema($column, $this->tableNames[$targetEntity]);
    }

    private function readManyToMany(ReflectionProperty $property, string $tableName): ?TableSchema
    {
        $attrs = $property->getAttributes(ManyToMany::class);
        if (empty($attrs)) {
            return null;
        }

        /** @var ManyToMany $attr */
        $attr = $attrs[0]->newInstance();

        // Only the owning side (no mappedBy) generates the pivot, so it isn't built twice.
        if ($attr->mappedBy !== null || $attr->targetEntity === null || !isset($this->tableNames[$attr->targetEntity])) {
            return null;
        }

        $otherTable = $this->tableNames[$attr->targetEntity];
        $pivotName = PivotNaming::tableName($tableName, $otherTable);

        $columnA = PivotNaming::singular($tableName) . '_id';
        $columnB = PivotNaming::singular($otherTable) . '_id';

        return new TableSchema($pivotName, [
            $columnA => new ColumnSchema($columnA, 'integer'),
            $columnB => new ColumnSchema($columnB, 'integer'),
        ], [
            $columnA => new ForeignKeySchema($columnA, $tableName),
            $columnB => new ForeignKeySchema($columnB, $otherTable),
        ]);
    }

    private function resolveTableName(string $class): string
    {
        $reflector = new ReflectionClass($class);
        $attrs = $reflector->getAttributes(Entity::class);
        /** @var Entity $entity */
        $entity = $attrs[0]->newInstance();

        return $entity->table ?? strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $reflector->getShortName())) . 's';
    }

    private function snakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
