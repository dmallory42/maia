<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;

/**
 * Middleware that enforces per-client request rate limits using a sliding time window.
 */
class RateLimit implements Middleware
{
    /**
     * Configure rate limiting with a maximum request count and time window.
     * @param int $maxRequests The maximum number of requests allowed per window.
     * @param int $windowSeconds The duration of the rate-limit window in seconds.
     * @param array $trustedProxies Remote IPs whose forwarded-for header should be trusted.
     * @param string $forwardedForHeader Header name containing the original client IP chain.
     * @return void
     */
    public function __construct(
        private int $maxRequests,
        private int $windowSeconds,
        private array $trustedProxies = [],
        private string $forwardedForHeader = 'X-Forwarded-For',
        private ?RateLimitStore $store = null,
        private string $namespace = 'default'
    ) {
        $this->store ??= new InMemoryRateLimitStore();
    }

    /**
     * Create a rate limiter that allows a fixed number of requests per minute.
     * @param int $maxRequests The maximum number of requests allowed per 60-second window.
     * @param array $trustedProxies Remote IPs whose forwarded-for header should be trusted.
     * @return self A new RateLimit instance with a 60-second window.
     */
    public static function perMinute(
        int $maxRequests,
        array $trustedProxies = [],
        ?RateLimitStore $store = null,
        string $namespace = 'default'
    ): self {
        return new self($maxRequests, 60, $trustedProxies, 'X-Forwarded-For', $store, $namespace);
    }

    /**
     * Enforce the rate limit, returning 429 if the client exceeds the allowed count.
     * @param Request $request The incoming HTTP request.
     * @param Closure $next The next middleware or route handler in the pipeline.
     * @return Response A 429 response with Retry-After if the limit is exceeded,
     *     the downstream response with rate-limit headers, or the unmodified downstream
     *     response when no stable client identity is available.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveClientKey($request);
        if ($key === null) {
            return $next($request);
        }

        $now = time();

        $current = $this->store->get($this->namespace, $key) ?? [
            'window_start' => $now,
            'count' => 0,
        ];

        if (($now - $current['window_start']) >= $this->windowSeconds) {
            $current = [
                'window_start' => $now,
                'count' => 0,
            ];
        }

        if ($current['count'] >= $this->maxRequests) {
            $retryAfter = max(1, $this->windowSeconds - ($now - $current['window_start']));

            return Response::json([
                'error' => true,
                'message' => 'Too many requests',
            ], 429)->withHeader('Retry-After', (string) $retryAfter);
        }

        $current['count']++;
        $this->store->set($this->namespace, $key, $current);

        $response = $next($request);
        $remaining = max(0, $this->maxRequests - $current['count']);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    /**
     * Determine the rate-limit bucket key from the client's IP or a global fallback.
     * @param Request $request The incoming HTTP request.
     * @return string|null The identifier used to track this client's request count.
     */
    private function resolveClientKey(Request $request): ?string
    {
        $remoteAddress = $request->remoteAddress();
        if ($remoteAddress !== null && $this->isTrustedProxy($remoteAddress)) {
            $forwardedAddress = $this->forwardedClientAddress($request);
            if ($forwardedAddress !== null) {
                return 'ip:' . $forwardedAddress;
            }
        }

        if ($remoteAddress !== null && filter_var($remoteAddress, FILTER_VALIDATE_IP) !== false) {
            return 'ip:' . $remoteAddress;
        }

        return null;
    }

    /**
     * Determine whether the immediate peer is a proxy whose forwarded headers should be trusted.
     * @param string $remoteAddress The server-reported remote IP address.
     * @return bool True when forwarded headers from this peer should be honored.
     */
    private function isTrustedProxy(string $remoteAddress): bool
    {
        return in_array($remoteAddress, $this->trustedProxies, true);
    }

    /**
     * Parse the left-most client IP from the configured forwarded-for header.
     * @param Request $request The incoming HTTP request.
     * @return string|null Client IP from the forwarded header, or null when missing/invalid.
     */
    private function forwardedClientAddress(Request $request): ?string
    {
        $forwardedFor = $request->header($this->forwardedForHeader);
        if (!is_string($forwardedFor) || trim($forwardedFor) === '') {
            return null;
        }

        $parts = array_map('trim', explode(',', $forwardedFor));
        $candidate = $parts[0] ?? '';
        if ($candidate === '' || filter_var($candidate, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $candidate;
    }
}
