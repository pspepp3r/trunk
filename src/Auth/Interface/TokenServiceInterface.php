<?php

namespace Trunk\Auth\Interface;

use Trunk\Auth\Exception\InvalidTokenException;

interface TokenServiceInterface
{
    /**
     * Issue a signed token encoding the given claims.
     */
    public function issue(array $claims): string;

    /**
     * Verify and decode a token, returning its claims.
     *
     * @throws InvalidTokenException if the token is missing, malformed, expired, or has an invalid signature.
     */
    public function verify(string $token): array;
}
