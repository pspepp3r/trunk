<?php

namespace Trunk\ORM;

use Trunk\Database\Connection;
use Trunk\ORM\Exception\InvalidEntityException;
use Trunk\ORM\BaseEntity;

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
        // Basic pluralization
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';
    }
}
