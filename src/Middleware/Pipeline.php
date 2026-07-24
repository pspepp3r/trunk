<?php

namespace Trunk\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response as ReactResponse;
use React\Promise\PromiseInterface;
use Trunk\Middleware\Interface\MiddlewareInterface;

use function count;
use function is_string;
use function React\Promise\resolve;

class Pipeline
{
    private array $middlewares = [];
    private ?ContainerInterface $container = null;

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function pipe(mixed $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function handle(ServerRequestInterface $request, callable $coreHandler): PromiseInterface
    {
        return $this->dispatch($request, 0, $coreHandler);
    }

    private function dispatch(ServerRequestInterface $request, int $index, callable $coreHandler): PromiseInterface
    {
        if ($index >= count($this->middlewares)) {
            $result = $coreHandler($request);
            return $result instanceof PromiseInterface ? $result : resolve($result);
        }

        $middleware = $this->middlewares[$index];
        $resolvedMiddleware = $this->resolveMiddleware($middleware);

        $next = fn(ServerRequestInterface $req) => $this->dispatch($req, $index + 1, $coreHandler);

        try {
            if ($resolvedMiddleware instanceof MiddlewareInterface) {
                return $resolvedMiddleware->process($request, $next);
            }

            if (is_callable($resolvedMiddleware)) {
                $result = $resolvedMiddleware($request, $next);
                return $result instanceof PromiseInterface ? $result : resolve($result);
            }

            throw new \Exception("Middleware is not callable or does not implement MiddlewareInterface");
        } catch (\Throwable $e) {
            return resolve(new ReactResponse(500, ['Content-Type' => 'application/json'], json_encode([
                'error' => 'Middleware Error',
                'message' => $e->getMessage()
            ])));
        }
    }

    private function resolveMiddleware(mixed $middleware): mixed
    {
        if (is_string($middleware)) {
            return $this->container ? $this->container->get($middleware) : new $middleware();
        }
        return $middleware;
    }
}
