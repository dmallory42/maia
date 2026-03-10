<?php

declare(strict_types=1);

$args = array_values(array_filter(
    array_slice($argv, 1),
    static fn (string $arg): bool => $arg !== '' && !str_starts_with($arg, '-')
));

$branch = $args[0] ?? null;
$base = $args[1] ?? 'main';

if ($branch === null) {
    fwrite(STDERR, "Usage: composer worktree:new -- <branch> [base]\n");
    exit(1);
}

if (preg_match('/^[A-Za-z0-9._\/-]+$/', $branch) !== 1) {
    fwrite(STDERR, "Invalid branch name.\n");
    exit(1);
}

if (preg_match('/^[A-Za-z0-9._\/-]+$/', $base) !== 1) {
    fwrite(STDERR, "Invalid base ref.\n");
    exit(1);
}

$root = dirname(__DIR__);
$worktreesDir = $root . '/.worktrees';
$slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $branch);
$slug = trim((string) $slug, '-');
$path = $worktreesDir . '/' . $slug;

if ($slug === '') {
    fwrite(STDERR, "Unable to derive worktree path from branch name.\n");
    exit(1);
}

if (file_exists($path)) {
    fwrite(STDERR, "Worktree path already exists: .worktrees/{$slug}\n");
    exit(1);
}

if (!is_dir($worktreesDir) && !mkdir($worktreesDir, 0777, true) && !is_dir($worktreesDir)) {
    fwrite(STDERR, "Unable to create .worktrees directory.\n");
    exit(1);
}

$branchExists = runGit(
    $root,
    ['show-ref', '--verify', '--quiet', 'refs/heads/' . $branch],
    false
);

if (!$branchExists['success']) {
    $fetchBase = runGit($root, ['fetch', 'origin', $base]);
    if (!$fetchBase['success']) {
        fwrite(STDERR, $fetchBase['output']);
        exit(1);
    }
}

$command = $branchExists['success']
    ? ['worktree', 'add', $path, $branch]
    : ['worktree', 'add', '-b', $branch, $path, $base];

$result = runGit($root, $command);
if (!$result['success']) {
    fwrite(STDERR, $result['output']);
    exit(1);
}

fwrite(STDOUT, "Created worktree: .worktrees/{$slug}\n");
fwrite(STDOUT, "Branch: {$branch}\n");
fwrite(STDOUT, "Path: {$path}\n");

/**
 * Run a git command in the repository root.
 * @param string $cwd Repository root path.
 * @param array<int, string> $parts Git command arguments.
 * @param bool $requireSuccess Whether to treat non-zero exit codes as failures.
 * @return array{success: bool, output: string}
 */
function runGit(string $cwd, array $parts, bool $requireSuccess = true): array
{
    $command = 'git';
    foreach ($parts as $part) {
        $command .= ' ' . escapeshellarg($part);
    }

    $descriptorSpec = [
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
    $success = $exitCode === 0;

    if (!$success && !$requireSuccess) {
        return ['success' => false, 'output' => (string) $stdout . (string) $stderr];
    }

    return [
        'success' => $success,
        'output' => (string) $stdout . (string) $stderr,
    ];
}
