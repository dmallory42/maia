#!/usr/bin/env php
<?php

declare(strict_types=1);

$minCoverage = 95.0;
$extraArgs = [];

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--min=')) {
        $minCoverage = (float) substr($arg, 6);
        continue;
    }

    $extraArgs[] = $arg;
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve repository root.\n");
    exit(1);
}

$phpunitBin = $root . '/vendor/bin/phpunit';
if (!is_file($phpunitBin)) {
    fwrite(STDERR, "phpunit binary not found. Run composer install first.\n");
    exit(1);
}

$junitPath = tempnam(sys_get_temp_dir(), 'maia_junit_');
if ($junitPath === false) {
    fwrite(STDERR, "Unable to create temporary JUnit file.\n");
    exit(1);
}

$command = [
    escapeshellarg(PHP_BINARY),
    escapeshellarg($phpunitBin),
    '--log-junit',
    escapeshellarg($junitPath),
];

foreach ($extraArgs as $arg) {
    $command[] = escapeshellarg($arg);
}

$full = implode(' ', $command);

passthru($full, $exitCode);

if (!is_file($junitPath)) {
    fwrite(STDERR, "JUnit report was not generated.\n");
    exit(1);
}

$xml = simplexml_load_file($junitPath);
unlink($junitPath);

if ($xml === false) {
    fwrite(STDERR, "Unable to parse JUnit report.\n");
    exit(1);
}

$stats = collectStats($xml);
$tests = $stats['tests'];
$failed = $stats['failures'] + $stats['errors'];
$passed = max(0, $tests - $failed);
$coverage = $tests > 0 ? ($passed / $tests) * 100 : 100.0;

printf("\nTest success rate: %.2f%% (%d/%d passed)\n", $coverage, $passed, $tests);
printf("Required minimum: %.2f%%\n", $minCoverage);

if ($coverage < $minCoverage) {
    fwrite(STDERR, "Test threshold not met.\n");
    exit(1);
}

if ($exitCode !== 0) {
    fwrite(STDERR, "PHPUnit exited non-zero but threshold was met. Allowing commit by configured threshold.\n");
}

exit(0);

/**
 * @return array{tests:int,failures:int,errors:int}
 */
function collectStats(SimpleXMLElement $xml): array
{
    $tests = 0;
    $failures = 0;
    $errors = 0;

    $cases = $xml->xpath('//testcase');
    if ($cases === false) {
        return [
            'tests' => 0,
            'failures' => 0,
            'errors' => 0,
        ];
    }

    $tests = count($cases);
    foreach ($cases as $case) {
        if (!$case instanceof SimpleXMLElement) {
            continue;
        }

        foreach ($case->children() as $child) {
            $name = $child->getName();
            if ($name === 'failure') {
                $failures++;
            } elseif ($name === 'error') {
                $errors++;
            }
        }
    }

    return [
        'tests' => $tests,
        'failures' => $failures,
        'errors' => $errors,
    ];
}
