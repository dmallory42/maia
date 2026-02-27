<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class MiddlewareAttribute
{
    /** @var array<int, string> */
    public array $middleware;

    public function __construct(string ...$middleware)
    {
        $this->middleware = $middleware;
    }
}
