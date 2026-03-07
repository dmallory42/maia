<?php

declare(strict_types=1);

namespace Maia\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;

/**
 * JwtService defines a framework component for this package.
 */
class JwtService
{
    /** @var array<int, string> */
    private const SUPPORTED_ALGORITHMS = ['HS256', 'HS384', 'HS512'];

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
        $this->algorithm = self::normalizeAlgorithm($this->algorithm);
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
