<?php

declare(strict_types=1);

namespace Maia\Orm;

use RuntimeException;

/**
 * Base exception for ORM and migration domain failures.
 */
class OrmException extends RuntimeException
{
}
