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
     * @var array<string, array{window_start: int, count: int}>
     */
    private static array $store = [];

    /**
     * Configure rate limiting with a maximum request count and time window.
     * @param int $maxRequests The maximum number of requests allowed per window.
     * @param int $windowSeconds The duration of the rate-limit window in seconds.
     * @return void
     */
    public function __construct(
        private int $maxRequests,
        private int $windowSeconds
    ) {
    }

    /**
     * Create a rate limiter that allows a fixed number of requests per minute.
     * @param int $maxRequests The maximum number of requests allowed per 60-second window.
     * @return self A new RateLimit instance with a 60-second window.
     */
    public static function perMinute(int $maxRequests): self
    {
        return new self($maxRequests, 60);
    }

    /**
     * Clear all stored rate-limit counters (useful for testing).
     * @return void
     */
    public static function reset(): void
    {
        self::$store = [];
    }

    /**
     * Enforce the rate limit, returning 429 if the client exceeds the allowed count.
     * @param Request $request The incoming HTTP request.
     * @param Closure $next The next middleware or route handler in the pipeline.
     * @return Response A 429 response with Retry-After if the limit is exceeded,
     *     otherwise the downstream response with rate-limit headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveClientKey($request);
        $now = time();

        $current = self::$store[$key] ?? [
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
        self::$store[$key] = $current;

        $response = $next($request);
        $remaining = max(0, $this->maxRequests - $current['count']);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    /**
     * Determine the rate-limit bucket key from the client's IP or a global fallback.
     * @param Request $request The incoming HTTP request.
     * @return string The identifier used to track this client's request count.
     */
    private function resolveClientKey(Request $request): string
    {
        $forwardedFor = $request->header('X-Forwarded-For');
        if (is_string($forwardedFor) && $forwardedFor !== '') {
            return $forwardedFor;
        }

        return 'global';
    }
}
