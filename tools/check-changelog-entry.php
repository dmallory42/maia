<?php

declare(strict_types=1);

$base = 'origin/main';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = substr($arg, strlen('--base='));
    }
}

$commands = [
    sprintf('git diff --name-only %s...HEAD 2>&1', escapeshellarg($base)),
    'git diff --cached --name-only 2>&1',
    'git diff --name-only 2>&1',
    'git ls-files --others --exclude-standard 2>&1',
];

$changedFiles = [];

foreach ($commands as $command) {
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "Unable to determine changed files.\n");
        foreach ($output as $line) {
            fwrite(STDERR, $line . PHP_EOL);
        }

        exit($exitCode);
    }

    foreach ($output as $line) {
        if (is_string($line) && $line !== '') {
            $changedFiles[$line] = true;
        }
    }
}

$changedFiles = array_keys($changedFiles);
$hasChangelogEntry = false;

foreach ($changedFiles as $file) {
    if (preg_match('#^\.changes/unreleased/.+\.md$#', $file) === 1) {
        $hasChangelogEntry = true;
        break;
    }
}

if ($hasChangelogEntry) {
    fwrite(STDOUT, "Changelog entry detected.\n");
    exit(0);
}

fwrite(STDERR, "Missing changelog entry.\n");
fwrite(STDERR, "Add a Markdown fragment under .changes/unreleased/ before merging this PR.\n");
exit(1);
