<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

class RouteMatch
{
    /**
     * @param array<string, string> $params
     * @param array<int, string> $middleware
     */
    public function __construct(
        public string $controller,
        public string $method,
        public array $params = [],
        public array $middleware = []
    ) {
    }
}
