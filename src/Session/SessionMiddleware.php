<?php

namespace Trunk\Session;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use Trunk\Config\Repository;
use Trunk\Middleware\Interface\MiddlewareInterface;
use Trunk\Session\Interface\SessionStoreInterface;

use function sprintf;

class SessionMiddleware implements MiddlewareInterface
{
    private SessionStoreInterface $store;
    private string $cookieName;
    private int $lifetime;

    public function __construct(SessionStoreInterface $store, Repository $config)
    {
        $this->store = $store;
        $this->cookieName = $config->get('session.cookie', 'trunk_session');
        $this->lifetime = $config->get('session.lifetime', 3600);
    }

    public function process(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        $cookies = $request->getCookieParams();
        $sessionId = $cookies[$this->cookieName] ?? null;

        if (!$sessionId) {
            $sessionId = bin2hex(random_bytes(16));
        }

        $sessionData = $this->store->get($sessionId);
        $session = new Session($sessionId, $sessionData);

        $request = $request->withAttribute('session', $session);

        return $next($request)->then(function ($response) use ($session) {
            if ($session->isModified()) {
                $this->store->set($session->getId(), $session->toArray());
            }

            $cookieValue = sprintf(
                '%s=%s; Path=/; Max-Age=%d; HttpOnly; SameSite=Lax',
                $this->cookieName,
                $session->getId(),
                $this->lifetime
            );

            return $response->withAddedHeader('Set-Cookie', $cookieValue);
        });
    }
}
