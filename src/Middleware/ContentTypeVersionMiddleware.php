<?php

namespace Trunk\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Trunk\Middleware\Interface\MiddlewareInterface;

/**
 * Optional, opt-in API versioning via a vendor media type, read from the Accept
 * header (falling back to Content-Type). Supports both common conventions:
 *
 *   Accept: application/vnd.trunk.v2+json        (version embedded in the type)
 *   Accept: application/vnd.trunk+json;version=2  (version as a parameter)
 *
 * The parsed version is attached to the request as the "api_version" attribute (a
 * string, e.g. "2"; config('versioning.default', '1') if the header is missing or
 * doesn't match), readable in any handler or FormRequest via:
 *
 *   $request->getAttribute('api_version')
 *
 * Not registered by default; add it yourself:
 *
 *   $app->use(ContentTypeVersionMiddleware::class);
 */
class ContentTypeVersionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        $header = $request->getHeaderLine('Accept') ?: $request->getHeaderLine('Content-Type');
        $version = $this->parseVersion($header) ?? (string) config('versioning.default', '1');

        return $next($request->withAttribute('api_version', $version));
    }

    private function parseVersion(string $header): ?string
    {
        // application/vnd.trunk.v2+json
        if (preg_match('/vnd\.[\w-]+\.v(\d+)\+/i', $header, $matches)) {
            return $matches[1];
        }

        // application/vnd.trunk+json;version=2
        if (preg_match('/[;,]\s*version=(\d+)/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
