<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
/**
 * Attribute that attaches middleware declarations to a controller class or action method.
 */
class MiddlewareAttribute
{
    /** @var array<int, string> */
    public array $middleware;

    /**
     * Capture the middleware class names that should run for the annotated target.
     * @param string... $middleware Middleware class names.
     * @return void
     */
    public function __construct(string ...$middleware)
    {
        $this->middleware = $middleware;
    }
}
