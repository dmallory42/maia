<?php

declare(strict_types=1);

namespace Maia\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Encodes and decodes JSON Web Tokens using the configured secret and algorithm.
 */
class JwtService
{
    /**
     * Initialize the JWT service with a signing secret and optional algorithm/TTL.
     * @param string $secret The secret used to sign and verify tokens.
     * @param string $algorithm JWT signing algorithm.
     * @param int $defaultTtlSeconds Default token lifetime in seconds when no exp claim is provided.
     * @return void
     */
    public function __construct(
        private string $secret,
        private string $algorithm = 'HS256',
        private int $defaultTtlSeconds = 3600
    ) {
    }

    /**
     * Encode a claims payload into a signed JWT string.
     * @param array $payload JWT claims payload; iat and exp are set automatically if absent.
     * @return string Encoded JWT token.
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
     * Decode and verify a JWT string, returning its claims payload.
     * @param string $token Encoded JWT token.
     * @return object Decoded claims payload.
     */
    public function decode(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, $this->algorithm));
    }
}
