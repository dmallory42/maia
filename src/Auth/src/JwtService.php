<?php

declare(strict_types=1);

namespace Maia\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JwtService defines a framework component for this package.
 */
class JwtService
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $secret Input value.
     * @param string $algorithm Input value.
     * @param int $defaultTtlSeconds Input value.
     * @return void Output value.
     */
    public function __construct(
        private string $secret,
        private string $algorithm = 'HS256',
        private int $defaultTtlSeconds = 3600
    ) {
    }

    /**
     * Encode and return string.
     * @param array $payload Input value.
     * @return string Output value.
     */
    public function encode(array $payload): string
    {
        $now = time();

        if (!isset($payload['iat'])) {
            $payload['iat'] = $now;
        }

        if (!isset($payload['exp'])) {
            $payload['exp'] = $now + $this->defaultTtlSeconds;
        }

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Decode and return object.
     * @param string $token Input value.
     * @return object Output value.
     */
    public function decode(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, $this->algorithm));
    }
}
