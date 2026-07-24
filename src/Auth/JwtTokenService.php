<?php

namespace Trunk\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Trunk\Auth\Exception\InvalidTokenException;
use Trunk\Auth\Interface\TokenServiceInterface;

class JwtTokenService implements TokenServiceInterface
{
    public function __construct(
        private readonly string $secret,
        private readonly string $algo = 'HS256',
        private readonly int $ttl = 3600,
    ) {}

    public function issue(array $claims): string
    {
        $now = time();

        $payload = [
            ...$claims,
            'iat' => $now,
            'exp' => $now + $this->ttl
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    public function verify(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));
            return (array) $decoded;
        } catch (\Throwable $e) {
            throw new InvalidTokenException('Invalid or expired token: ' . $e->getMessage(), 0, $e);
        }
    }
}
