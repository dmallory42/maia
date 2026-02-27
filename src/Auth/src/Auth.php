<?php

declare(strict_types=1);

namespace Maia\Auth;

/**
 * Auth defines a framework component for this package.
 */
class Auth
{
    /**
     * Jwt and return JwtMiddleware.
     * @param string $secret Input value.
     * @param string $algorithm Input value.
     * @return JwtMiddleware Output value.
     */
    public static function jwt(string $secret, string $algorithm = 'HS256'): JwtMiddleware
    {
        return new JwtMiddleware(new JwtService($secret, $algorithm));
    }
}
