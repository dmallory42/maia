<?php

declare(strict_types=1);

require_once __DIR__ . '/utils.php';

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

if (!isValidRef($branch)) {
    fwrite(STDERR, "Invalid branch name.\n");
    exit(1);
}

if (!isValidRef($base)) {
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
    fwrite(STDERR, "Worktree path already exists: .worktrees/{$slug} (derived from '{$branch}')\n");
    exit(1);
}

if (!ensureDirectory($worktreesDir)) {
    fwrite(STDERR, "Unable to create .worktrees directory.\n");
    exit(1);
}

$branchExists = runGit(
    $root,
    ['show-ref', '--verify', '--quiet', 'refs/heads/' . $branch]
);

if (!$branchExists['success']) {
    $hasLocalBase = runGit($root, ['show-ref', '--verify', '--quiet', 'refs/heads/' . $base]);
    $hasRemoteBase = runGit($root, ['show-ref', '--verify', '--quiet', 'refs/remotes/origin/' . $base]);

    if (!$hasLocalBase['success'] && !$hasRemoteBase['success']) {
        fwrite(STDOUT, "Fetching {$base} from origin...\n");
        $fetchBase = runGit($root, ['fetch', 'origin', $base]);
        if (!$fetchBase['success']) {
            fwrite(STDERR, $fetchBase['output']);
            exit(1);
        }
    }
}

$command = $branchExists['success']
    ? ['worktree', 'add', '--', $path, $branch]
    : ['worktree', 'add', '-b', $branch, '--', $path, $base];

$result = runGit($root, $command);
if (!$result['success']) {
    fwrite(STDERR, $result['output']);
    exit(1);
}

fwrite(STDOUT, "Created worktree: .worktrees/{$slug}\n");
fwrite(STDOUT, "Branch: {$branch}\n");
fwrite(STDOUT, "Path: {$path}\n");
