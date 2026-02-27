<?php

declare(strict_types=1);

namespace Maia\Auth;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;
use Maia\Core\Middleware\Middleware;

class RateLimit implements Middleware
{
    /**
     * @var array<string, array{window_start: int, count: int}>
     */
    private static array $store = [];

    public function __construct(
        private int $maxRequests,
        private int $windowSeconds
    ) {
    }

    public static function perMinute(int $maxRequests): self
    {
        return new self($maxRequests, 60);
    }

    public static function reset(): void
    {
        self::$store = [];
    }

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

    private function resolveClientKey(Request $request): string
    {
        $forwardedFor = $request->header('X-Forwarded-For');
        if (is_string($forwardedFor) && $forwardedFor !== '') {
            return $forwardedFor;
        }

        return 'global';
    }
}
