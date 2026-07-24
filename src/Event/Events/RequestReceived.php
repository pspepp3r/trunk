<?php

namespace Trunk\Event\Events;

use Psr\Http\Message\ServerRequestInterface;

class RequestReceived
{
    public function __construct(public readonly ServerRequestInterface $request) {}
}
