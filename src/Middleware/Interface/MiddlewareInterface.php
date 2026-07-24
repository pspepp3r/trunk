<?php

namespace Trunk\Middleware\Interface;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

interface MiddlewareInterface
{
    /**
     * Process an incoming server request.
     *
     * @param ServerRequestInterface $request
     * @param callable $next The next middleware/handler in the pipeline. Must return PromiseInterface or Response.
     * @return PromiseInterface
     */
    public function process(ServerRequestInterface $request, callable $next): PromiseInterface;
}
