<?php

declare(strict_types=1);

$slug = null;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '' || str_starts_with($arg, '-')) {
        continue;
    }

    $slug = $arg;
    break;
}

if ($slug === null) {
    fwrite(STDERR, "Usage: composer changelog:new -- <slug>\n");
    exit(1);
}

if (str_ends_with($slug, '.md')) {
    $slug = substr($slug, 0, -3);
}

if ($slug === '' || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $slug) !== 1) {
    fwrite(STDERR, "Invalid changelog slug.\n");
    fwrite(STDERR, "Use lowercase letters, numbers, dots, underscores, or hyphens.\n");
    exit(1);
}

$root = dirname(__DIR__);
$directory = $root . '/.changes/unreleased';
$path = $directory . '/' . $slug . '.md';

if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    fwrite(STDERR, "Unable to create changelog directory: {$directory}\n");
    exit(1);
}

if (file_exists($path)) {
    fwrite(STDERR, "Changelog entry already exists: .changes/unreleased/{$slug}.md\n");
    exit(1);
}

$template = <<<MD
- Describe the user-facing change.
MD;

$written = file_put_contents($path, $template . PHP_EOL);
if ($written === false) {
    fwrite(STDERR, "Unable to write changelog entry.\n");
    exit(1);
}

fwrite(STDOUT, "Created .changes/unreleased/{$slug}.md\n");
