<?php

namespace Trunk\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Trunk\Cache\Interface\CacheInterface;
use Trunk\Http\Response;
use Trunk\Middleware\Interface\MiddlewareInterface;

/** Optional, opt-in, shared-cache-backed rate limiter - see the Middleware guide's rate limiting section. */
class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function process(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        $max = (int) config('rate_limit.max', 60);
        $window = (int) config('rate_limit.window', 60);
        $key = 'trunk:rate-limit:' . $this->resolveKey($request);

        return $this->cache->increment($key)->then(function (int $count) use ($key, $window, $max, $request, $next) {
            if ($count === 1) {
                $this->cache->expire($key, $window);
            }

            if ($count > $max) {
                return Response::json([
                    'error' => 'Too Many Requests',
                    'message' => "Rate limit of {$max} requests per {$window}s exceeded.",
                ], 429);
            }

            return $next($request);
        });
    }

    /** Buckets by client IP by default - override to key by user/API key instead. */
    private function resolveKey(ServerRequestInterface $request): string
    {
        return $request->getServerParams()['REMOTE_ADDR'] ?? 'global';
    }
}
