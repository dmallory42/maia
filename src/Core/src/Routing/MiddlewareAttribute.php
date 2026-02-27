<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
/**
 * MiddlewareAttribute defines a framework component for this package.
 */
class MiddlewareAttribute
{
    /** @var array<int, string> */
    public array $middleware;

    /**
     * Create an instance with configured dependencies and defaults.
     * @param string... $middleware Input value.
     * @return void Output value.
     */
    public function __construct(string ...$middleware)
    {
        $this->middleware = $middleware;
    }
}
