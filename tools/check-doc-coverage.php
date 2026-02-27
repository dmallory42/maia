#!/usr/bin/env php
<?php

declare(strict_types=1);

use phpDocumentor\Reflection\DocBlockFactory;

require __DIR__ . '/../vendor/autoload.php';

$minCoverage = 95.0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--min=')) {
        $minCoverage = (float) substr($arg, 6);
    }
}

$factory = DocBlockFactory::createInstance();
$srcRoot = realpath(__DIR__ . '/../src');
if ($srcRoot === false) {
    fwrite(STDERR, "Unable to resolve src directory.\n");
    exit(1);
}

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcRoot));
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    if (!str_ends_with($path, '.php')) {
        continue;
    }

    if (!preg_match('#/src/[^/]+/src/.+\.php$#', $path)) {
        continue;
    }

    $files[] = $path;
}
sort($files);

$total = 0;
$documented = 0;
$findings = [];

foreach ($files as $path) {
    $code = file_get_contents($path);
    if (!is_string($code)) {
        continue;
    }

    $tokens = token_get_all($code);
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            continue;
        }

        if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
            if (isClassConstantFetch($tokens, $i)) {
                continue;
            }
            if (isAnonymousClass($tokens, $i)) {
                continue;
            }

            $nameInfo = nextNamedToken($tokens, $i + 1);
            if ($nameInfo === null) {
                continue;
            }

            $total++;
            [$ok, $reason] = evaluateDoc(
                getDocCommentBefore($tokens, declarationStartIndex($tokens, $i)),
                $factory,
                [],
                false
            );

            if ($ok) {
                $documented++;
            } else {
                $findings[] = sprintf('%s:%d class %s - %s', $path, $token[2], $nameInfo['text'], $reason);
            }

            continue;
        }

        if ($token[0] === T_FUNCTION) {
            $nameInfo = functionName($tokens, $i + 1);
            if ($nameInfo === null) {
                continue;
            }

            [$name, $nameIndex] = $nameInfo;
            $params = parseSignatureParams($tokens, $nameIndex + 1);

            $total++;
            [$ok, $reason] = evaluateDoc(
                getDocCommentBefore($tokens, declarationStartIndex($tokens, $i)),
                $factory,
                $params,
                true
            );

            if ($ok) {
                $documented++;
            } else {
                $findings[] = sprintf('%s:%d method %s - %s', $path, $token[2], $name, $reason);
            }
        }
    }
}

$coverage = $total > 0 ? ($documented / $total) * 100 : 100.0;

printf("Documentation coverage: %.2f%% (%d/%d)\n", $coverage, $documented, $total);
printf("Required minimum: %.2f%%\n", $minCoverage);

if ($findings !== []) {
    echo "\nMissing/incomplete docblocks:\n";
    foreach ($findings as $finding) {
        echo ' - ' . $finding . "\n";
    }
}

if ($coverage < $minCoverage) {
    exit(1);
}

exit(0);

/**
 * @param array<int, mixed> $tokens
 */
function isAnonymousClass(array $tokens, int $index): bool
{
    for ($i = $index - 1; $i >= 0; $i--) {
        $t = $tokens[$i];
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return is_array($t) && $t[0] === T_NEW;
    }

    return false;
}

/**
 * @param array<int, mixed> $tokens
 */
function isClassConstantFetch(array $tokens, int $index): bool
{
    for ($i = $index - 1; $i >= 0; $i--) {
        $t = $tokens[$i];
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return is_array($t) && $t[0] === T_DOUBLE_COLON;
    }

    return false;
}

/**
 * @param array<int, mixed> $tokens
 * @return array{text: string, index: int}|null
 */
function nextNamedToken(array $tokens, int $start): ?array
{
    $count = count($tokens);

    for ($i = $start; $i < $count; $i++) {
        $t = $tokens[$i];
        if (!is_array($t)) {
            continue;
        }

        if (in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        if ($t[0] === T_STRING) {
            return ['text' => $t[1], 'index' => $i];
        }

        return null;
    }

    return null;
}

/**
 * @param array<int, mixed> $tokens
 * @return array{0: string, 1: int}|null
 */
function functionName(array $tokens, int $start): ?array
{
    $count = count($tokens);
    for ($i = $start; $i < $count; $i++) {
        $t = $tokens[$i];

        if (is_array($t)) {
            if (in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if ($t[0] === T_STRING) {
                return [$t[1], $i];
            }

            return null;
        }

        if ($t === '&') {
            continue;
        }

        if ($t === '(') {
            return null;
        }
    }

    return null;
}

/**
 * @param array<int, mixed> $tokens
 * @return array<int, string>
 */
function parseSignatureParams(array $tokens, int $start): array
{
    $count = count($tokens);
    $open = null;

    for ($i = $start; $i < $count; $i++) {
        if ($tokens[$i] === '(') {
            $open = $i;
            break;
        }
    }

    if ($open === null) {
        return [];
    }

    $depth = 0;
    $close = null;
    for ($i = $open; $i < $count; $i++) {
        $t = $tokens[$i];
        if ($t === '(') {
            $depth++;
        } elseif ($t === ')') {
            $depth--;
            if ($depth === 0) {
                $close = $i;
                break;
            }
        }
    }

    if ($close === null) {
        return [];
    }

    $params = [];
    $segment = [];
    $segmentDepth = 0;

    $slice = array_slice($tokens, $open + 1, $close - $open - 1);
    foreach ($slice as $t) {
        if ($t === ',' && $segmentDepth === 0) {
            if ($segment !== []) {
                $name = extractParamName($segment);
                if ($name !== null) {
                    $params[] = $name;
                }
            }
            $segment = [];
            continue;
        }

        if ($t === '(' || $t === '[' || $t === '{') {
            $segmentDepth++;
        } elseif ($t === ')' || $t === ']' || $t === '}') {
            $segmentDepth = max(0, $segmentDepth - 1);
        }

        $segment[] = $t;
    }

    if ($segment !== []) {
        $name = extractParamName($segment);
        if ($name !== null) {
            $params[] = $name;
        }
    }

    return $params;
}

/**
 * @param array<int, mixed> $segment
 */
function extractParamName(array $segment): ?string
{
    foreach ($segment as $t) {
        if (is_array($t) && $t[0] === T_VARIABLE) {
            return ltrim($t[1], '$');
        }
    }

    return null;
}

/**
 * @param array<int, mixed> $tokens
 */
function getDocCommentBefore(array $tokens, int $index): ?string
{
    for ($i = $index - 1; $i >= 0; $i--) {
        $t = $tokens[$i];
        if (is_array($t)) {
            if (in_array($t[0], [T_WHITESPACE, T_COMMENT], true)) {
                continue;
            }

            if ($t[0] === T_DOC_COMMENT) {
                return $t[1];
            }

            return null;
        }

        if (trim($t) === '') {
            continue;
        }

        return null;
    }

    return null;
}

/**
 * @param array<int, string> $paramNames
 * @return array{0: bool, 1: string}
 */
function evaluateDoc(?string $docComment, DocBlockFactory $factory, array $paramNames, bool $requireReturn): array
{
    if ($docComment === null) {
        return [false, 'missing docblock'];
    }

    try {
        $doc = $factory->create($docComment);
    } catch (Throwable $e) {
        return [false, 'invalid docblock: ' . $e->getMessage()];
    }

    if (trim((string) $doc->getSummary()) === '') {
        return [false, 'missing summary'];
    }

    if ($paramNames !== []) {
        $paramTags = $doc->getTagsByName('param');
        if (count($paramTags) < count($paramNames)) {
            return [false, 'missing @param tags'];
        }

        $tagNames = [];
        foreach ($paramTags as $tag) {
            $tagNames[] = ltrim((string) $tag->getVariableName(), '$');
        }

        foreach ($paramNames as $paramName) {
            if (!in_array($paramName, $tagNames, true)) {
                return [false, sprintf('missing @param for $%s', $paramName)];
            }
        }
    }

    if ($requireReturn && count($doc->getTagsByName('return')) === 0) {
        return [false, 'missing @return tag'];
    }

    return [true, 'ok'];
}

/**
 * @param array<int, mixed> $tokens
 */
function declarationStartIndex(array $tokens, int $index): int
{
    $allowed = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT, T_READONLY, T_WHITESPACE, T_COMMENT];
    $attributeDepth = 0;

    for ($i = $index - 1; $i >= 0; $i--) {
        $t = $tokens[$i];
        if (!is_array($t)) {
            if ($t === ']') {
                $attributeDepth++;
                continue;
            }
            if ($t === '[' && $attributeDepth > 0) {
                $attributeDepth--;
                continue;
            }
            if ($attributeDepth > 0) {
                continue;
            }
            if (trim((string) $t) === '') {
                continue;
            }
            return $i + 1;
        }

        if ($attributeDepth > 0) {
            continue;
        }

        if (in_array($t[0], $allowed, true)) {
            continue;
        }

        if ($t[0] === T_ATTRIBUTE) {
            continue;
        }

        if ($t[0] === T_DOC_COMMENT) {
            return $i + 1;
        }

        return $i + 1;
    }

    return 0;
}
