<?php

namespace Trunk\Container;

use Exception;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use Trunk\Container\Exception\ContainerException;
use Trunk\Container\Exception\NotFoundException;

use function array_key_exists;

class Container implements ContainerInterface
{
    private array $entries = [];

    public function get(string $id)
    {
        if (array_key_exists($id, $this->entries)) {
            $entry = $this->entries[$id];
            return $entry instanceof \Closure ? $entry($this) : $entry;
        }

        if (class_exists($id)) {
            return $this->resolve($id);
        }

        throw new NotFoundException("Entry or class '{$id}' not found in container.");
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]) || class_exists($id);
    }

    public function set(string $id, mixed $value): void
    {
        $this->entries[$id] = $value;
    }

    public function singleton(string $id, mixed $value): void
    {
        $this->set($id, function (ContainerInterface $c) use ($value) {
            static $resolved = null;
            if ($resolved === null) {
                $resolved = match (true) {
                    $value instanceof \Closure => $value($c),
                    is_string($value) && class_exists($value) => $c->get($value),
                    default => $value,
                };
            }
            return $resolved;
        });
    }

    private function resolve(string $class)
    {
        try {
            $reflector = new ReflectionClass($class);
            if (!$reflector->isInstantiable()) {
                throw new ContainerException("Class '{$class}' is not instantiable.");
            }

            $constructor = $reflector->getConstructor();
            if ($constructor === null) {
                return new $class();
            }

            $parameters = $constructor->getParameters();
            $dependencies = $this->resolveDependencies($parameters);

            return $reflector->newInstanceArgs($dependencies);
        } catch (Exception $e) {
            throw new ContainerException("Error resolving class '{$class}': " . $e->getMessage(), 0, $e);
        }
    }

    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new ContainerException("Cannot resolve parameter '{$parameter->getName()}' without a type or default value.");
            }
        }
        return $dependencies;
    }
}
