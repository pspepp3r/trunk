<?php

namespace Trunk\Session;

use function array_key_exists;

class Session
{
    private string $id;
    private array $data = [];
    private array $flash = [];
    private array $flashOld = [];
    private bool $isModified = false;

    public function __construct(string $id, array $data = [])
    {
        $this->id = $id;
        $this->data = $data['_data'] ?? [];
        $this->flashOld = $data['_flash'] ?? [];
        $this->isModified = false;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }
        return $this->flashOld[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->isModified = true;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key], $this->flashOld[$key]);
        $this->isModified = true;
    }

    public function flash(string $key, mixed $value): void
    {
        $this->flash[$key] = $value;
        $this->isModified = true;
    }

    public function regenerate(): void
    {
        $this->id = bin2hex(random_bytes(16));
        $this->isModified = true;
    }

    public function toArray(): array
    {
        return [
            '_data' => $this->data,
            '_flash' => $this->flash
        ];
    }

    public function isModified(): bool
    {
        return $this->isModified;
    }
}
