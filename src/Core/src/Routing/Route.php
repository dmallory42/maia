<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    /** @param array<int, string> $middleware */
    public function __construct(
        public string $path,
        public string $method = 'GET',
        public array $middleware = []
    ) {
    }
}
