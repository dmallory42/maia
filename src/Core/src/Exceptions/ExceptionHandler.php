<?php

declare(strict_types=1);

namespace Maia\Core\Exceptions;

use Maia\Core\Http\Response;
use Throwable;

/**
 * ExceptionHandler defines a framework component for this package.
 */
class ExceptionHandler
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param bool $debug Input value.
     * @return void Output value.
     */
    public function __construct(private bool $debug)
    {
    }

    /**
     * Handle and return Response.
     * @param Throwable $exception Input value.
     * @return Response Output value.
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
