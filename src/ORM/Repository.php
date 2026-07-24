<?php

namespace Trunk\ORM;

use React\Promise\PromiseInterface;
use ReflectionClass;
use Trunk\Database\Connection;

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

    public function persist(object $entity): PromiseInterface
    {
        $reflector = new ReflectionClass($entity);
        $properties = $reflector->getProperties();

        $data = [];
        $id = null;

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $name = $property->getName();

            // Check if property is initialized
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
            // Insert
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
                    $pkProp->setAccessible(true);
                    $pkProp->setValue($entity, $insertId);
                }
                return $entity;
            });
        } else {
            // Update
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
        $pkProp->setAccessible(true);
        $id = $pkProp->getValue($entity);

        if ($id === null) {
            return resolve(false);
        }

        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->query($sql, [$id])->then(fn() => true);
    }

    protected function mapRowToEntity(array $row): object
    {
        $entity = new ReflectionClass($this->entityClass)->newInstanceWithoutConstructor();
        $reflector = new ReflectionClass($entity);

        foreach ($row as $field => $value) {
            $propertyName = $this->camelCase($field);
            if ($reflector->hasProperty($propertyName)) {
                $property = $reflector->getProperty($propertyName);
                $property->setAccessible(true);
                $property->setValue($entity, $value);
            }
        }

        return $entity;
    }

    private function snakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    private function camelCase(string $input): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
    }
}
