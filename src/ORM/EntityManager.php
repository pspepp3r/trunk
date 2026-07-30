<?php

namespace Trunk\ORM;

use ReflectionClass;
use ReflectionProperty;
use React\Promise\PromiseInterface;
use Trunk\Database\Connection;
use Trunk\Database\ORM\Attributes\Column;
use Trunk\Database\ORM\Attributes\ManyToMany;
use Trunk\Database\ORM\Attributes\ManyToOne;
use Trunk\Database\ORM\Attributes\OneToMany;
use Trunk\Database\ORM\Attributes\OneToOne;
use Trunk\Database\Schema\Diff\PivotNaming;
use Trunk\ORM\Exception\InvalidEntityException;
use Trunk\ORM\BaseEntity;

use function React\Promise\all;
use function React\Promise\resolve;

class EntityManager
{
    private Connection $db;
    private array $mappings = [];
    private array $repositories = [];

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Map an Entity class to a database table.
     */
    public function map(string $entityClass, string $table): void
    {
        $this->mappings[$entityClass] = $table;
    }

    /**
     * Get the Repository instance for a specific Entity class.
     */
    public function getRepository(string $entityClass): Repository
    {
        if (isset($this->repositories[$entityClass])) {
            return $this->repositories[$entityClass];
        }

        if (!is_a($entityClass, BaseEntity::class, true)) {
            throw new InvalidEntityException("'{$entityClass}' must implement " . BaseEntity::class . ' to be managed by the ORM.');
        }

        $table = $this->mappings[$entityClass] ?? $this->resolveTableName($entityClass);

        // Check if there is a custom Repository defined by convention, e.g. App\Repositories\UserRepository
        $customRepoClass = str_replace('Entities', 'Repositories', $entityClass) . 'Repository';
        $repository = (class_exists($customRepoClass)) ? new $customRepoClass($entityClass, $table, $this->db) : new Repository($entityClass, $table, $this->db);

        $this->repositories[$entityClass] = $repository;
        return $repository;
    }

    private function resolveTableName(string $entityClass): string
    {
        $parts = explode('\\', $entityClass);
        $name = end($parts);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';
    }

    /**
     * Explicit relationship loading (no lazy-loading proxy) - see the Database guide's Relationships section.
     *
     * @return PromiseInterface<object|object[]|null>
     */
    public function loadRelation(object $entity, string $property): PromiseInterface
    {
        $entityClass = get_class($entity);
        $reflector = new ReflectionClass($entityClass);
        $reflectionProperty = $reflector->getProperty($property);

        $manyToOne = $reflectionProperty->getAttributes(ManyToOne::class);
        if (!empty($manyToOne)) {
            /** @var ManyToOne $attr */
            $attr = $manyToOne[0]->newInstance();
            return $this->loadOwningSide($entity, $entityClass, $reflectionProperty, $attr->targetEntity);
        }

        $oneToOne = $reflectionProperty->getAttributes(OneToOne::class);
        if (!empty($oneToOne)) {
            /** @var OneToOne $attr */
            $attr = $oneToOne[0]->newInstance();
            if ($attr->mappedBy !== null) {
                return $this->loadInverseSingle($entity, $attr->targetEntity, $attr->mappedBy);
            }
            return $this->loadOwningSide($entity, $entityClass, $reflectionProperty, $attr->targetEntity);
        }

        $oneToMany = $reflectionProperty->getAttributes(OneToMany::class);
        if (!empty($oneToMany)) {
            /** @var OneToMany $attr */
            $attr = $oneToMany[0]->newInstance();
            if ($attr->targetEntity === null || $attr->mappedBy === null) {
                return resolve([]);
            }
            $fkColumn = $this->snakeCase($attr->mappedBy) . '_id';
            return $this->getRepository($attr->targetEntity)->findBy($fkColumn, $this->primaryKeyValue($entity));
        }

        $manyToMany = $reflectionProperty->getAttributes(ManyToMany::class);
        if (!empty($manyToMany)) {
            /** @var ManyToMany $attr */
            $attr = $manyToMany[0]->newInstance();
            return $this->loadManyToMany($entity, $entityClass, $attr);
        }

        throw new \InvalidArgumentException("Property '{$property}' on {$entityClass} has no relationship attribute.");
    }

    private function loadOwningSide(object $entity, string $entityClass, ReflectionProperty $property, ?string $targetEntity): PromiseInterface
    {
        if ($targetEntity === null) {
            return resolve(null);
        }

        $table = $this->mappings[$entityClass] ?? $this->resolveTableName($entityClass);
        $fkColumn = $this->snakeCase($property->getName()) . '_id';
        $pk = $this->primaryKeyValue($entity);

        return $this->db->query("SELECT {$fkColumn} FROM {$table} WHERE id = ?", [$pk])
            ->then(function ($result) use ($targetEntity, $fkColumn) {
                if (empty($result->rows) || $result->rows[0][$fkColumn] === null) {
                    return null;
                }
                return $this->getRepository($targetEntity)->find($result->rows[0][$fkColumn]);
            });
    }

    private function loadInverseSingle(object $entity, ?string $targetEntity, string $mappedBy): PromiseInterface
    {
        if ($targetEntity === null) {
            return resolve(null);
        }

        $fkColumn = $this->snakeCase($mappedBy) . '_id';

        return $this->getRepository($targetEntity)->findOneBy($fkColumn, $this->primaryKeyValue($entity));
    }

    private function loadManyToMany(object $entity, string $entityClass, ManyToMany $attr): PromiseInterface
    {
        if ($attr->targetEntity === null) {
            return resolve([]);
        }

        $ownTable = $this->mappings[$entityClass] ?? $this->resolveTableName($entityClass);
        $targetTable = $this->mappings[$attr->targetEntity] ?? $this->resolveTableName($attr->targetEntity);
        $pivot = PivotNaming::tableName($ownTable, $targetTable);

        // Self-referential ManyToMany isn't supported - see the Database guide's Relationships section.
        $ownColumn = PivotNaming::singular($ownTable) . '_id';
        $targetColumn = PivotNaming::singular($targetTable) . '_id';
        $pk = $this->primaryKeyValue($entity);

        return $this->db->query("SELECT {$targetColumn} FROM {$pivot} WHERE {$ownColumn} = ?", [$pk])
            ->then(function ($result) use ($attr, $targetColumn) {
                $ids = array_column($result->rows, $targetColumn);
                if (empty($ids)) {
                    return resolve([]);
                }

                $repository = $this->getRepository($attr->targetEntity);
                return all(array_map(fn($id) => $repository->find($id), $ids));
            });
    }

    private function primaryKeyValue(object $entity): mixed
    {
        $reflector = new ReflectionClass($entity);

        foreach ($reflector->getProperties() as $property) {
            $columnAttrs = $property->getAttributes(Column::class);
            $isPrimary = (!empty($columnAttrs) && $columnAttrs[0]->newInstance()->primary) || $property->getName() === 'id';

            if ($isPrimary) {
                if (PHP_VERSION_ID >= 80100) {
                    $property->setAccessible(true);
                }
                return $property->getValue($entity);
            }
        }

        throw new \RuntimeException(get_class($entity) . ' has no primary key property.');
    }

    private function snakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
