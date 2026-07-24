<?php

namespace Trunk\Session;

use Trunk\Session\Interface\SessionStoreInterface;

class MemorySessionStore implements SessionStoreInterface
{
    private array $sessions = [];

    public function get(string $id): array
    {
        return $this->sessions[$id] ?? [];
    }

    public function set(string $id, array $data): void
    {
        $this->sessions[$id] = $data;
    }

    public function destroy(string $id): void
    {
        unset($this->sessions[$id]);
    }
}
