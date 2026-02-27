<?php

declare(strict_types=1);

namespace Maia\Auth;

class Auth
{
    public static function jwt(string $secret, string $algorithm = 'HS256'): JwtMiddleware
    {
        return new JwtMiddleware(new JwtService($secret, $algorithm));
    }
}
