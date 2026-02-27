<?php

declare(strict_types=1);

namespace Maia\Core\Middleware;

use Closure;
use Maia\Core\Http\Request;
use Maia\Core\Http\Response;

interface Middleware
{
    public function handle(Request $request, Closure $next): Response;
}
