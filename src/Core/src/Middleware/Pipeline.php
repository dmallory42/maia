<?php

declare(strict_types=1);

namespace Maia\Core\Middleware;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;

class Pipeline
{
    /** @param array<int, Middleware> $middleware */
    public function __construct(private array $middleware)
    {
    }

    public function run(Request $request, Closure $handler): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            /** @param Closure(Request): Response $next */
            fn (Closure $next, Middleware $middleware): Closure => fn (Request $req): Response => $middleware->handle($req, $next),
            $handler
        );

        return $pipeline($request);
    }
}
