<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

/**
 * Value object representing a successfully matched route with its controller, method, and parameters.
 */
class RouteMatch
{
    /**
     * Capture the result of matching a request to a registered route.
     * @param string $controller Fully qualified class name of the matched controller.
     * @param string $method Name of the controller method to invoke.
     * @param array $params Named parameters extracted from the URL path.
     * @param array $middleware Middleware class names declared on the route.
     * @return void
     */
    public function __construct(
        public string $controller,
        public string $method,
        public array $params = [],
        public array $middleware = []
    ) {
    }
}
