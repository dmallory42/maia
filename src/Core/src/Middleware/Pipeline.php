<?php

declare(strict_types=1);

namespace Maia\Core\Middleware;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;

/**
 * Pipeline defines a framework component for this package.
 */
class Pipeline
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param array $middleware Input value.
     * @return void Output value.
     */
    public function __construct(private array $middleware)
    {
    }

    /**
     * Run and return Response.
     * @param Request $request Input value.
     * @param Closure $handler Input value.
     * @return Response Output value.
     */
    public function run(Request $request, Closure $handler): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            /** @param Closure(Request): Response $next */
            fn (Closure $next, Middleware $middleware): Closure => fn (Request $req): Response => $middleware
                ->handle($req, $next),
            $handler
        );

        return $pipeline($request);
    }
}
