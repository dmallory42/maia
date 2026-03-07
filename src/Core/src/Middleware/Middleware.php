<?php

declare(strict_types=1);

namespace Maia\Core\Middleware;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;

/**
 * Contract for HTTP middleware that can inspect or transform a request/response pair.
 */
interface Middleware
{
    /**
     * Handle the request and either short-circuit or delegate to the next middleware.
     * @param Request $request The incoming HTTP request.
     * @param Closure $next The next middleware or final request handler.
     * @return Response The resulting HTTP response.
     */
    public function handle(Request $request, Closure $next): Response;
}
