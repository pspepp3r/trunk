<?php

namespace Trunk\Event\Events;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ResponseSent
{
    public function __construct(
        public readonly ServerRequestInterface $request,
        public readonly ResponseInterface $response,
        public readonly float $durationMs,
    ) {}
}
