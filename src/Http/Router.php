<?php

namespace Trunk\Http;

use Closure;
use Exception;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Trunk\Middleware\Pipeline;
use Trunk\ORM\EntityManager;
use Trunk\ORM\BaseEntity;
use Trunk\Validation\Exception\ValidationException;
use Trunk\Validation\FormRequest;

use function array_key_exists;
use function array_slice;
use function count;
use function is_array;
use function is_string;
use function React\Promise\all;
use function React\Promise\resolve;

class Router
{
    private array $routes = [];
    private ?ContainerInterface $container = null;

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function addRoute(string $method, string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->normalizePath($path),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function patch(string $path, callable|array|string $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function dispatch(ServerRequestInterface $request): PromiseInterface
    {
        $requestMethod = $request->getMethod();
        $requestPath = $this->normalizePath($request->getUri()->getPath());

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            $routePattern = $this->compilePattern($route['path']);
            if (preg_match($routePattern, $requestPath, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                foreach ($params as $name => $value) {
                    $request = $request->withAttribute($name, $value);
                }

                if (empty($route['middleware'])) {
                    return $this->executeHandler($route['handler'], $request, $params);
                }

                $pipeline = new Pipeline($this->container);
                foreach ($route['middleware'] as $middleware) {
                    $pipeline->pipe($middleware);
                }

                return $pipeline->handle($request, fn(ServerRequestInterface $req) => $this->executeHandler($route['handler'], $req, $params));
            }
        }

        return resolve(Response::json(['error' => 'Not Found', 'message' => "Route '{$requestMethod} {$requestPath}' not found"], 404));
    }

    private function normalizePath(string $path): string
    {
        return '/' . trim($path, '/');
    }

    private function compilePattern(string $path): string
    {
        // Replace {param} with (?P<param>[^/]+)
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        return "#^$pattern\$#";
    }

    private function executeHandler(mixed $handler, ServerRequestInterface $request, array $params): PromiseInterface
    {
        try {
            $callable = $this->resolveHandler($handler);
            $parameters = $this->reflectCallable($callable)->getParameters();
            $argument = $this->prepareFirstArgument($parameters, $request);
        } catch (ValidationException $e) {
            return resolve(Response::json([
                'error' => 'Validation Failed',
                'errors' => $e->errors,
            ], 422));
        } catch (Exception $e) {
            return resolve(Response::json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage()
            ], 500));
        }

        return $this->resolveRouteModelBindings($parameters, $params)
            ->then(function (?array $boundParams) use ($callable, $argument) {
                if ($boundParams === null) {
                    return Response::json(['error' => 'Not Found', 'message' => 'Resource not found'], 404);
                }

                $result = $callable($argument, ...$boundParams);

                return $result instanceof PromiseInterface ? $result : resolve($result);
            })
            ->then(null, fn(\Throwable $e) => Response::json(['error' => 'Internal Server Error', 'message' => $e->getMessage()], 500));
    }

    /**
     * If the handler's first parameter type-hints a FormRequest subclass, build and validate
     * it from the incoming request; otherwise pass the raw PSR-7 request through unchanged.
     *
     * @param ReflectionParameter[] $parameters
     */
    private function prepareFirstArgument(array $parameters, ServerRequestInterface $request): ServerRequestInterface|FormRequest
    {
        if (empty($parameters)) {
            return $request;
        }

        $type = $parameters[0]->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin() || !is_subclass_of($type->getName(), FormRequest::class)) {
            return $request;
        }

        $formRequestClass = $type->getName();
        $formRequest = new $formRequestClass($request);
        $formRequest->validate();

        return $formRequest;
    }

    /**
     * Resolves the handler's remaining parameters (after the request/FormRequest) against the
     * matched route segments. A parameter whose name matches a route segment and whose type is a
     * non-builtin class is treated as an implicit route-model binding: it's resolved by looking
     * the raw segment value up via the ORM's EntityManager repository for that class. Resolves to
     * null (signalling a 404) if any bound model isn't found; otherwise resolves to the ordered
     * list of arguments to pass to the handler after the first one.
     *
     * @param ReflectionParameter[] $parameters
     */
    private function resolveRouteModelBindings(array $parameters, array $routeParams): PromiseInterface
    {
        $remaining = array_slice($parameters, 1);

        if (empty($remaining)) {
            return resolve([]);
        }

        $promises = [];

        foreach ($remaining as $parameter) {
            $name = $parameter->getName();

            if (!array_key_exists($name, $routeParams)) {
                return resolve(null);
            }

            $rawValue = $routeParams[$name];
            $type = $parameter->getType();

            if (
                $this->container !== null
                && $type instanceof ReflectionNamedType
                && !$type->isBuiltin()
                && is_a($type->getName(), BaseEntity::class, true)
                && $this->container->has(EntityManager::class)
            ) {
                $promises[$name] = $this->container->get(EntityManager::class)
                    ->getRepository($type->getName())
                    ->find($rawValue);
            } else {
                $promises[$name] = resolve($rawValue);
            }
        }

        return all($promises)->then(function (array $resolved) {
            foreach ($resolved as $value) {
                if ($value === null) {
                    return null;
                }
            }

            return array_values($resolved);
        });
    }

    private function reflectCallable(callable $callable): ReflectionFunctionAbstract
    {
        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        return new ReflectionFunction(Closure::fromCallable($callable));
    }

    private function resolveHandler(mixed $handler): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = $this->container ? $this->container->get($class) : new $class();
            return [$instance, $method];
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler);
            $instance = $this->container ? $this->container->get($class) : new $class();
            return [$instance, $method];
        }

        throw new Exception("Invalid route handler provided.");
    }
}
