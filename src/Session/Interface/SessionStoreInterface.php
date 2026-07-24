<?php

namespace Trunk\Session\Interface;

interface SessionStoreInterface
{
    public function get(string $id): array;
    public function set(string $id, array $data): void;
    public function destroy(string $id): void;
}
