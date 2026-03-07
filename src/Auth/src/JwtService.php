<?php

declare(strict_types=1);

namespace Maia\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;

/**
 * Encodes and decodes JSON Web Tokens using the configured secret and algorithm.
 */
class JwtService
{
    /** @var array<int, string> */
    private const SUPPORTED_ALGORITHMS = ['HS256', 'HS384', 'HS512'];

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
        $this->algorithm = self::normalizeAlgorithm($this->algorithm);
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

    /**
     * Normalize algorithm and return string.
     * @param string $algorithm Input value.
     * @return string Output value.
     */
    private static function normalizeAlgorithm(string $algorithm): string
    {
        $normalized = strtoupper($algorithm);

        if (in_array($normalized, self::SUPPORTED_ALGORITHMS, true)) {
            return $normalized;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Unsupported JWT algorithm [%s]. Supported algorithms: %s.',
                $algorithm,
                implode(', ', self::SUPPORTED_ALGORITHMS)
            )
        );
    }
}
