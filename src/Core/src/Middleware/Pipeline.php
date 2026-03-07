<?php

declare(strict_types=1);

namespace Maia\Core\Middleware;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;

/**
 * Executes a list of middleware around a final request handler.
 */
class Pipeline
{
    /**
     * Build the pipeline from an ordered list of middleware instances.
     * @param array $middleware Middleware instances to run in order.
     * @return void
     */
    public function __construct(private array $middleware)
    {
    }

    /**
     * Run the request through the middleware chain and final handler.
     * @param Request $request The incoming HTTP request.
     * @param Closure $handler The final handler invoked after all middleware.
     * @return Response The response produced by the pipeline.
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
