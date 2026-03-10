<?php

declare(strict_types=1);

/** Create a directory if it does not already exist (race-safe). */
function ensureDirectory(string $path): bool
{
    return is_dir($path) || (mkdir($path, 0777, true) && is_dir($path));
}

/** Validate a git ref name (branch or tag). Rejects leading dots, consecutive dots, and trailing .lock. */
function isValidRef(string $ref): bool
{
    if ($ref === '') {
        return false;
    }

    if (preg_match('/^[A-Za-z0-9._\/-]+$/', $ref) !== 1) {
        return false;
    }

    // Reject patterns git itself forbids or that pose path-traversal risk.
    if (
        str_starts_with($ref, '.') ||
        str_starts_with($ref, '/') ||
        str_ends_with($ref, '/') ||
        str_ends_with($ref, '.lock') ||
        str_contains($ref, '..') ||
        str_contains($ref, '//')
    ) {
        return false;
    }

    return true;
}

/**
 * Run a git command in the given working directory.
 * @param string $cwd Working directory.
 * @param array<int, string> $parts Git command arguments.
 * @return array{success: bool, output: string}
 */
function runGit(string $cwd, array $parts): array
{
    $command = 'git';
    foreach ($parts as $part) {
        $command .= ' ' . escapeshellarg($part);
    }

    $descriptorSpec = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
    if (!is_resource($process)) {
        return ['success' => false, 'output' => "Unable to start git command.\n"];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [
        'success' => $exitCode === 0,
        'output' => (string) $stdout . (string) $stderr,
    ];
}
