<?php

namespace Trunk\Console\Command\Interface;

interface CommandInterface
{
    /**
     * One-line summary shown in `php trunk help`.
     */
    public static function description(): string;

    public function handle(array $args): void;
}
