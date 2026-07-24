<?php

namespace Trunk\Tests\Fixtures;

use Psr\Log\AbstractLogger;

class SpyLogger extends AbstractLogger
{
    /** @var array{level: mixed, message: string, context: array}[] */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}
