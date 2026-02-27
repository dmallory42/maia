<?php

declare(strict_types=1);

namespace Maia\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    public function __construct(
        private string $secret,
        private string $algorithm = 'HS256',
        private int $defaultTtlSeconds = 3600
    ) {
    }

    /** @param array<string, mixed> $payload */
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

    public function decode(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, $this->algorithm));
    }
}
