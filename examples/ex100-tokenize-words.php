<?php

/**
 * ex100 — word-level tokenize: typos, inflections, synonyms, and unrelated words.
 *
 * Single words isolate how the tokenizer behaves: it matches spelling and subword
 * pieces, not meaning. Typos often share token prefixes; synonyms usually do not.
 *
 * Usage: php examples/ex100-tokenize-words.php
 */

declare(strict_types=1);

use Tivins\LlmLib\LLM;

require_once __DIR__ . '/../vendor/autoload.php';

$llm = new LLM(
    endpoint: 'http://127.0.0.1:8080',
    timeoutSeconds: 60,
);

/** @var list<array{category: string, left: string, right: string, expectation: string}> */
$comparisons = [
    [
        'category' => 'Identical spelling',
        'left' => 'house',
        'right' => 'house',
        'expectation' => 'Same spelling → identical token sequence.',
    ],
    [
        'category' => 'Related form (plural)',
        'left' => 'house',
        'right' => 'houses',
        'expectation' => 'Related form, often shares a leading subword token.',
    ],
    [
        'category' => 'Typo',
        'left' => 'hello',
        'right' => 'helllo',
        'expectation' => 'Misspelling often keeps the first token(s), then diverges.',
    ],
    [
        'category' => 'Typo',
        'left' => 'quickly',
        'right' => 'quickkly',
        'expectation' => 'Double letter typo: partial overlap on the shared stem.',
    ],
    [
        'category' => 'Typo',
        'left' => 'happy',
        'right' => 'happpy',
        'expectation' => 'Extra letter inside the word: may still share stem tokens.',
    ],
    [
        'category' => 'Typo',
        'left' => 'beautiful',
        'right' => 'beautifull',
        'expectation' => 'Trailing typo can split into completely different pieces.',
    ],
    [
        'category' => 'Close meaning (synonym)',
        'left' => 'happy',
        'right' => 'joyful',
        'expectation' => 'Close meaning, but different spelling → usually no token overlap.',
    ],
    [
        'category' => 'Close meaning (synonym)',
        'left' => 'house',
        'right' => 'dwelling',
        'expectation' => 'Synonyms are different words; token IDs rarely match.',
    ],
    [
        'category' => 'Close meaning (synonym)',
        'left' => 'fast',
        'right' => 'quick',
        'expectation' => 'Same idea, unrelated surface forms → unrelated tokens.',
    ],
    [
        'category' => 'Close meaning (synonym)',
        'left' => 'car',
        'right' => 'automobile',
        'expectation' => 'Synonyms with different spellings do not share tokenizer IDs.',
    ],
    [
        'category' => 'Unrelated words',
        'left' => 'house',
        'right' => 'car',
        'expectation' => 'Unrelated nouns → no overlap (control case).',
    ],
    [
        'category' => 'Opposite meaning',
        'left' => 'happy',
        'right' => 'sad',
        'expectation' => 'Opposite meaning still means different tokens, like synonyms.',
    ],
];

echo "=== Word-level token similarity ===\n";
echo "Endpoint: {$llm->endpoint}/tokenize\n";
echo "Note: token IDs reflect spelling/subwords, not semantic similarity.\n\n";

$stats = [
    'exact' => 0,
    'partial' => 0,
    'none' => 0,
];

foreach ($comparisons as $index => $comparison) {
    $left = tokenizeWord($llm, $comparison['left']);
    $right = tokenizeWord($llm, $comparison['right']);
    $verdict = wordVerdict($left, $right);
    $stats[$verdict['level']]++;

    echo sprintf("--- [%d] %s ---\n", $index + 1, $comparison['category']);
    echo formatWordLine('left ', $comparison['left'], $left);
    echo formatWordLine('right', $comparison['right'], $right);
    echo "  verdict: {$verdict['label']}";
    if ($verdict['detail'] !== '') {
        echo " ({$verdict['detail']})";
    }
    echo "\n";
    echo "  expected: {$comparison['expectation']}\n\n";
}

echo "=== Summary ===\n";
echo sprintf(
    "  exact=%d | partial=%d | none=%d (over %d pairs)\n",
    $stats['exact'],
    $stats['partial'],
    $stats['none'],
    count($comparisons),
);
echo "\n";
echo "Takeaways:\n";
echo "  • Typos and inflections often show PARTIAL overlap (shared subword tokens).\n";
echo "  • Synonyms and opposites usually show NONE — meaning is not in token IDs.\n";
echo "  • Only identical spellings guarantee EXACT token sequences.\n";

/**
 * @return list<int>
 */
function tokenizeWord(LLM $llm, string $word): array
{
    try {
        return $llm->tokenize($word);
    } catch (Throwable $e) {
        fwrite(STDERR, "Tokenize failed for \"{$word}\": {$e->getMessage()}" . PHP_EOL);
        exit(1);
    }
}

/**
 * @param list<int> $tokens
 */
function formatWordLine(string $side, string $word, array $tokens): string
{
    return sprintf(
        "  %-5s %-14s %s\n",
        $side,
        $word,
        formatTokenPreview($tokens),
    );
}

/**
 * @param list<int> $tokens
 */
function formatTokenPreview(array $tokens): string
{
    if ($tokens === []) {
        return '[]';
    }

    return '[' . implode(', ', $tokens) . ']';
}

/**
 * @param list<int> $left
 * @param list<int> $right
 *
 * @return array{level: 'exact'|'partial'|'none', label: string, detail: string}
 */
function wordVerdict(array $left, array $right): array
{
    if ($left === $right) {
        return [
            'level' => 'exact',
            'label' => 'EXACT — same token sequence',
            'detail' => '',
        ];
    }

    $sharedPrefix = sharedPrefixLength($left, $right);
    $sharedIds = array_values(array_intersect($left, $right));

    if ($sharedPrefix > 0) {
        return [
            'level' => 'partial',
            'label' => 'PARTIAL — shared prefix',
            'detail' => sprintf('%d leading token(s), ids %s', $sharedPrefix, formatTokenPreview($sharedIds)),
        ];
    }

    if ($sharedIds !== []) {
        return [
            'level' => 'partial',
            'label' => 'PARTIAL — shared token(s)',
            'detail' => 'ids ' . formatTokenPreview($sharedIds),
        ];
    }

    return [
        'level' => 'none',
        'label' => 'NONE — no shared token',
        'detail' => '',
    ];
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
