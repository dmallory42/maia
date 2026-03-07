<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
/**
 * PHP attribute that maps an HTTP method and path to a controller method.
 */
class Route
{
    /**
     * Define a route for a controller method.
     * @param string $path URL path pattern, may include {param} placeholders.
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.).
     * @param array $middleware List of middleware class names to apply to this route.
     * @return void
     */
    public function __construct(
        public string $path,
        public string $method = 'GET',
        public array $middleware = []
    ) {
    }
}
