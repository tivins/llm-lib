<?php

/**
 * ex090 — tokenize: explore token vectors for similar and dissimilar phrases.
 *
 * Calls LLM::tokenize() against a llama.cpp-compatible server (POST /tokenize).
 * Compares token counts, leading prefixes, and position-wise overlap between pairs.
 *
 * Usage: php examples/ex090-tokenize-phrases.php
 */

declare(strict_types=1);

use Tivins\LlmLib\LLM;

require_once __DIR__ . '/../vendor/autoload.php';

$llm = new LLM(
    endpoint: 'http://127.0.0.1:8080',
    timeoutSeconds: 60,
);

/** @var list<array{label: string, phrases: list<string>}> */
$groups = [
    [
        'label' => 'Similar — greeting variants (punctuation, spacing)',
        'phrases' => [
            'Hello, how are you?',
            'Hello, how are you',
            'Hello how are you?',
        ],
    ],
    [
        'label' => 'Similar — near-duplicate English sentence',
        'phrases' => [
            'The quick brown fox jumps over the lazy dog.',
            'The quick brown fox jumps over a lazy dog.',
            'A quick brown fox jumps over the lazy dog.',
        ],
    ],
    [
        'label' => 'Dissimilar — unrelated topics',
        'phrases' => [
            'Deploy the staging environment before Friday.',
            'What is the capital of Australia?',
            'sudo apt install nginx',
        ],
    ],
];

echo "=== Tokenize exploration ===\n";
echo "Endpoint: {$llm->endpoint}/tokenize\n\n";

/** @var array<string, list<int>> */
$vectors = [];

foreach ($groups as $group) {
    echo "--- {$group['label']} ---\n";

    foreach ($group['phrases'] as $phrase) {
        try {
            $tokens = $llm->tokenize($phrase);
        } catch (Throwable $e) {
            fwrite(STDERR, "Tokenize failed for \"{$phrase}\": {$e->getMessage()}" . PHP_EOL);
            exit(1);
        }

        $vectors[$phrase] = $tokens;
        $preview = formatTokenPreview($tokens, 12);

        echo sprintf(
            "  [%3d tokens] %s\n    ids: %s\n",
            count($tokens),
            $phrase,
            $preview,
        );
    }

    echo compareGroup($group['phrases'], $vectors);
    echo "\n";
}

echo "=== Cross-group spot check (similar vs dissimilar) ===\n";
$greeting = 'Hello, how are you?';
$deploy = 'Deploy the staging environment before Friday.';

if (isset($vectors[$greeting], $vectors[$deploy])) {
    echo comparePair('greeting', $greeting, $vectors[$greeting], 'deploy line', $deploy, $vectors[$deploy]);
}

/**
 * @param list<int> $tokens
 */
function formatTokenPreview(array $tokens, int $max): string
{
    if ($tokens === []) {
        return '[]';
    }

    $slice = array_slice($tokens, 0, $max);
    $suffix = count($tokens) > $max ? ', …' : '';

    return '[' . implode(', ', $slice) . $suffix . ']';
}

/**
 * @param list<string> $phrases
 * @param array<string, list<int>> $vectors
 */
function compareGroup(array $phrases, array $vectors): string
{
    $out = "  Pairwise comparison:\n";

    for ($i = 0; $i < count($phrases); ++$i) {
        for ($j = $i + 1; $j < count($phrases); ++$j) {
            $a = $phrases[$i];
            $b = $phrases[$j];
            $out .= '  ' . comparePair("A{$i}", $a, $vectors[$a], "B{$j}", $b, $vectors[$b]);
        }
    }

    return $out;
}

/**
 * @param list<int> $left
 * @param list<int> $right
 */
function comparePair(
    string $leftLabel,
    string $leftText,
    array $left,
    string $rightLabel,
    string $rightText,
    array $right,
): string {
    $prefix = sharedPrefixLength($left, $right);
    $overlap = positionOverlap($left, $right);
    $jaccard = tokenJaccard($left, $right);

    return sprintf(
        "    %s ↔ %s | prefix=%d | pos-match=%.0f%% | jaccard=%.0f%% | len %d vs %d\n      %s\n      %s\n",
        $leftLabel,
        $rightLabel,
        $prefix,
        $overlap * 100,
        $jaccard * 100,
        count($left),
        count($right),
        $leftText,
        $rightText,
    );
}

/** @param list<int> $left @param list<int> $right */
function sharedPrefixLength(array $left, array $right): int
{
    $limit = min(count($left), count($right));
    $shared = 0;

    for ($i = 0; $i < $limit; ++$i) {
        if ($left[$i] !== $right[$i]) {
            break;
        }
        ++$shared;
    }

    return $shared;
}

/** @param list<int> $left @param list<int> $right */
function positionOverlap(array $left, array $right): float
{
    $limit = min(count($left), count($right));
    if ($limit === 0) {
        return 0.0;
    }

    $matches = 0;
    for ($i = 0; $i < $limit; ++$i) {
        if ($left[$i] === $right[$i]) {
            ++$matches;
        }
    }

    return $matches / $limit;
}

/** @param list<int> $left @param list<int> $right */
function tokenJaccard(array $left, array $right): float
{
    if ($left === [] && $right === []) {
        return 1.0;
    }

    $setA = array_flip($left);
    $setB = array_flip($right);
    $intersection = count(array_intersect_key($setA, $setB));
    $union = count($setA + $setB);

    return $union === 0 ? 0.0 : $intersection / $union;
}
