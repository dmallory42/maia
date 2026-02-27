<?php

declare(strict_types=1);

namespace Maia\Core\Middleware;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;

/**
 * Middleware defines a framework component for this package.
 */
interface Middleware
{
    /**
     * Handle and return Response.
     * @param Request $request Input value.
     * @param Closure $next Input value.
     * @return Response Output value.
     */
    public function handle(Request $request, Closure $next): Response;
}
