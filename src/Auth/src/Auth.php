<?php

declare(strict_types=1);

namespace Maia\Auth;

/**
 * Factory for building pre-configured authentication middleware.
 */
class Auth
{
    /**
     * Create a JWT authentication middleware with the given signing secret.
     * @param string $secret The HMAC secret used to sign and verify tokens.
     * @param string $algorithm The HMAC algorithm (HS256, HS384, or HS512).
     * @return JwtMiddleware Middleware that validates Bearer tokens on incoming requests.
     */
    public static function jwt(string $secret, string $algorithm = 'HS256'): JwtMiddleware
    {
        return new JwtMiddleware(new JwtService($secret, $algorithm));
    }
}
