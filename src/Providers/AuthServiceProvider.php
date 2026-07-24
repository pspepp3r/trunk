<?php

namespace Trunk\Providers;

use Trunk\Auth\Interface\TokenServiceInterface;
use Trunk\Auth\JwtTokenService;
use Trunk\Config\Repository;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(TokenServiceInterface::class, function ($c) {
            $config = $c->get(Repository::class);

            return new JwtTokenService(
                $config->get('auth.secret', 'trunk-insecure-default-secret-please-change-me'),
                $config->get('auth.algo', 'HS256'),
                $config->get('auth.ttl', 3600)
            );
        });
    }
}
