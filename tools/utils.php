<?php

declare(strict_types=1);

/** Create a directory if it does not already exist (race-safe). */
function ensureDirectory(string $path): bool
{
    return is_dir($path) || (mkdir($path, 0777, true) && is_dir($path));
}
