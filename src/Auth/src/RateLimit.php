<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;

/**
 * RateLimit defines a framework component for this package.
 */
class RateLimit implements Middleware
{
    /**
     * @var array<string, array{window_start: int, count: int}>
     */
    private static array $store = [];

    /**
     * Create an instance with configured dependencies and defaults.
     * @param int $maxRequests Input value.
     * @param int $windowSeconds Input value.
     * @return void Output value.
     */
    public function __construct(
        private int $maxRequests,
        private int $windowSeconds
    ) {
    }

    /**
     * Per minute and return self.
     * @param int $maxRequests Input value.
     * @return self Output value.
     */
    public static function perMinute(int $maxRequests): self
    {
        return new self($maxRequests, 60);
    }

    /**
     * Reset and return void.
     * @return void Output value.
     */
    public static function reset(): void
    {
        self::$store = [];
    }

    /**
     * Handle and return Response.
     * @param Request $request Input value.
     * @param Closure $next Input value.
     * @return Response Output value.
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
     * Resolve client key and return string.
     * @param Request $request Input value.
     * @return string Output value.
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
