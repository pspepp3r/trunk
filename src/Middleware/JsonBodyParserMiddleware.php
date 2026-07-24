<?php

namespace Trunk\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Trunk\Middleware\Interface\MiddlewareInterface;

class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (str_contains(strtolower($contentType), 'application/json')) {
            $body = (string)$request->getBody();
            if ($body !== '') {
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request = $request->withParsedBody($data);
                }
            }
        }

        return $next($request);
    }
}
