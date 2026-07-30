<?php

namespace Trunk\ORM;

use React\Promise\PromiseInterface;
use ReflectionClass;
use ReflectionProperty;
use Trunk\Database\Connection;
use Trunk\Database\ORM\Attributes\ManyToMany;
use Trunk\Database\ORM\Attributes\ManyToOne;
use Trunk\Database\ORM\Attributes\OneToMany;
use Trunk\Database\ORM\Attributes\OneToOne;

use function count;
use function React\Promise\resolve;
use function sprintf;

class Repository
{
    protected string $entityClass;
    protected string $table;
    protected string $primaryKey = 'id';
    protected Connection $db;

    public function __construct(string $entityClass, string $table, Connection $db)
    {
        $this->entityClass = $entityClass;
        $this->table = $table;
        $this->db = $db;
    }

    public function find(mixed $id): PromiseInterface
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1";

        return $this->db->query($sql, [$id])->then(function ($result) {
            if (empty($result->rows)) {
                return null;
            }
            return $this->mapRowToEntity($result->rows[0]);
        });
    }

    public function findAll(): PromiseInterface
    {
        $sql = "SELECT * FROM {$this->table}";

        return $this->db->query($sql)->then(function ($result) {
            $entities = [];
            foreach ($result->rows as $row) {
                $entities[] = $this->mapRowToEntity($row);
            }
            return $entities;
        });
    }

    /**
     * Used by EntityManager::loadRelation() for the "many" side of a relationship
     * (e.g. all Posts where author_id = $userId) - also handy on its own for any
     * simple equality lookup that isn't the primary key.
     *
     * @return PromiseInterface<object[]>
     */
    public function findBy(string $column, mixed $value): PromiseInterface
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = ?";

        return $this->db->query($sql, [$value])->then(function ($result) {
            return array_map(fn(array $row) => $this->mapRowToEntity($row), $result->rows);
        });
    }

    /**
     * @return PromiseInterface<object|null>
     */
    public function findOneBy(string $column, mixed $value): PromiseInterface
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = ? LIMIT 1";

        return $this->db->query($sql, [$value])->then(function ($result) {
            return empty($result->rows) ? null : $this->mapRowToEntity($result->rows[0]);
        });
    }

    public function persist(object $entity): PromiseInterface
    {
        $reflector = new ReflectionClass($entity);
        $properties = $reflector->getProperties();

        $data = [];
        $id = null;

        foreach ($properties as $property) {
            if ($this->isRelationProperty($property)) {
                continue;
            }

            if (PHP_VERSION_ID >= 80100) {
                $property->setAccessible(true);
            }
            $name = $property->getName();

            if (!$property->isInitialized($entity)) {
                continue;
            }

            $value = $property->getValue($entity);

            if ($name === $this->primaryKey) {
                $id = $value;
            } else {
                $data[$this->snakeCase($name)] = $value;
            }
        }

        if ($id === null) {
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($data), '?');

            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)%s",
                $this->table,
                implode(', ', $fields),
                implode(', ', $placeholders),
                $this->db->grammar()->insertReturningClause($this->primaryKey)
            );

            return $this->db->query($sql, array_values($data))->then(function ($result) use ($entity, $reflector) {
                // Set the primary key back on the entity
                $insertId = $this->db->grammar()->extractInsertId($result, $this->primaryKey);
                if ($insertId !== null) {
                    $pkProp = $reflector->getProperty($this->primaryKey);
                    if (PHP_VERSION_ID >= 80100) {
                        $pkProp->setAccessible(true);
                    }
                    $pkProp->setValue($entity, $insertId);
                }
                return $entity;
            });
        } else {
            $sets = [];
            foreach (array_keys($data) as $field) {
                $sets[] = "{$field} = ?";
            }

            $sql = sprintf(
                "UPDATE %s SET %s WHERE %s = ?",
                $this->table,
                implode(', ', $sets),
                $this->primaryKey
            );

            $params = array_values($data);
            $params[] = $id;

            return $this->db->query($sql, $params)->then(fn() => $entity);
        }
    }

    public function delete(object $entity): PromiseInterface
    {
        $reflector = new ReflectionClass($entity);
        $pkProp = $reflector->getProperty($this->primaryKey);
        if (PHP_VERSION_ID >= 80100) {
            $pkProp->setAccessible(true);
        }
        $id = $pkProp->getValue($entity);

        if ($id === null) {
            return resolve(false);
        }

        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->query($sql, [$id])->then(fn() => true);
    }

    protected function mapRowToEntity(array $row): object
    {
        $entity = (new ReflectionClass($this->entityClass))->newInstanceWithoutConstructor();
        $reflector = new ReflectionClass($entity);

        foreach ($row as $field => $value) {
            $propertyName = $this->camelCase($field);
            if ($reflector->hasProperty($propertyName)) {
                $property = $reflector->getProperty($propertyName);
                if (PHP_VERSION_ID >= 80100) {
                    $property->setAccessible(true);
                }
                $property->setValue($entity, $value);
            }
        }

        return $entity;
    }

    private function snakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /** Relationship-typed properties aren't cascade-persisted - see the Database guide's Relationships section. */
    private function isRelationProperty(ReflectionProperty $property): bool
    {
        return !empty($property->getAttributes(ManyToOne::class))
            || !empty($property->getAttributes(OneToOne::class))
            || !empty($property->getAttributes(OneToMany::class))
            || !empty($property->getAttributes(ManyToMany::class));
    }

    private function camelCase(string $input): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
    }
}
