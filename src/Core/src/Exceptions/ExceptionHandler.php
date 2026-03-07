<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

use Maia\Core\Http\Response;
use Throwable;

/**
 * Converts framework and runtime exceptions into JSON HTTP responses.
 */
class ExceptionHandler
{
    /**
     * Build the exception handler with debug-mode behavior control.
     * @param bool $debug Whether unexpected exceptions should include debugging details.
     * @return void
     */
    public function __construct(private bool $debug)
    {
    }

    /**
     * Convert an exception into a JSON response suitable for API clients.
     * @param Throwable $exception The exception to render.
     * @return Response JSON error response with the appropriate status code.
     */
    public function handle(Throwable $exception): Response
    {
        if ($exception instanceof ValidationException) {
            return Response::json([
                'error' => true,
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], $exception->status());
        }

        if ($exception instanceof HttpException) {
            return Response::json([
                'error' => true,
                'message' => $exception->getMessage(),
            ], $exception->status());
        }

        if ($this->debug) {
            return Response::json([
                'error' => true,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
                'file' => $exception->getFile() . ':' . $exception->getLine(),
                'trace' => $exception->getTrace(),
            ], 500);
        }

        return Response::json([
            'error' => true,
            'message' => 'Internal server error',
        ], 500);
    }
}
