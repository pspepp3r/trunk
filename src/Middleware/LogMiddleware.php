<?php

namespace Trunk\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Trunk\Log\Logger;
use Trunk\Middleware\Interface\MiddlewareInterface;

class LogMiddleware implements MiddlewareInterface
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        $start = microtime(true);
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        $this->logger->info("Request started: {method} {path}", [
            'method' => $method,
            'path' => $path
        ]);

        return $next($request)->then(function ($response) use ($method, $path, $start) {
            $duration = round((microtime(true) - $start) * 1000, 2);
            $status = $response->getStatusCode();

            $this->logger->info("Request finished: {method} {path} - Status: {status} ({duration}ms)", [
                'method' => $method,
                'path' => $path,
                'status' => $status,
                'duration' => $duration
            ]);

            return $response;
        });
    }
}
