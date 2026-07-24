<?php

namespace Trunk\Database\Driver\Interface;

use React\Promise\PromiseInterface;

interface DriverInterface
{
    /**
     * Runs a query and resolves with a Trunk\Database\QueryResult.
     */
    public function query(string $sql, array $params = []): PromiseInterface;
}
