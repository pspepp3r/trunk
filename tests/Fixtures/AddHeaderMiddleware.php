<?php

namespace Trunk\Tests\Fixtures;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Trunk\Middleware\Interface\MiddlewareInterface;

class AddHeaderMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        return $next($request)->then(function ($response) {
            return $response->withHeader('X-Test-Middleware', 'applied');
        });
    }
}
