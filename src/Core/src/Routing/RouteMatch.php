<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

/**
 * RouteMatch defines a framework component for this package.
 */
class RouteMatch
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $controller Input value.
     * @param string $method Input value.
     * @param array $params Input value.
     * @param array $middleware Input value.
     * @return void Output value.
     */
    public function __construct(
        public string $controller,
        public string $method,
        public array $params = [],
        public array $middleware = []
    ) {
    }
}
