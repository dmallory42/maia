<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
/**
 * Route defines a framework component for this package.
 */
class Route
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $path Input value.
     * @param string $method Input value.
     * @param array $middleware Input value.
     * @return void Output value.
     */
    public function __construct(
        public string $path,
        public string $method = 'GET',
        public array $middleware = []
    ) {
    }
}
