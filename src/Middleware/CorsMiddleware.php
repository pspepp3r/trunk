<?php

namespace Trunk\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Trunk\Http\Response;
use Trunk\Middleware\Interface\MiddlewareInterface;

use function React\Promise\resolve;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = Response::empty(204, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
                'Access-Control-Max-Age' => '86400',
            ]);
            return resolve($response);
        }

        return $next($request)->then(fn($response) => $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With'));
    }
}
