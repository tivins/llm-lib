<?php

/**
 * ex120 — generate embeddings via OpenAI-compatible POST /v1/embeddings.
 *
 * Requires a llama.cpp (or compatible) server with an embedding model loaded,
 * e.g. bge-m3 started with --embedding --pooling cls on port 8081.
 *
 * Usage: php examples/ex120-embeddings.php
 */

declare(strict_types=1);

use Tivins\LlmLib\EmbeddingOptions;
use Tivins\LlmLib\LLM;

require_once __DIR__ . '/../vendor/autoload.php';

$llm = new LLM(
    endpoint: 'http://127.0.0.1:8081',
    defaultModel: 'bge-m3-Q8_0.gguf',
    timeoutSeconds: 120,
);

$options = new EmbeddingOptions(encodingFormat: 'float');

$texts = [
    'The cat sits on the mat.',
    'A feline rests on a rug.',
    'Stock markets rallied after the earnings report.',
];


$response = $llm->embeddings($texts, $options);

echo "Model: {$response->model}\n";
echo "Tokens: {$response->usage->totalTokens}\n";
echo 'Duration: ' . number_format($response->duration ?? 0.0, 1) . " ms\n\n";

foreach ($response->embeddings as $embedding) {
    $preview = array_slice($embedding->vector, 0, 5);
    $previewText = implode(', ', array_map(static fn (float $v): string => sprintf('%.4f', $v), $preview));
    echo "[$embedding->index] dim=" . count($embedding->vector) . " first=[{$previewText}, …]\n";
    echo "    text: {$texts[$embedding->index]}\n\n";
}

$vectors = $response->vectors();
$similarity = cosineSimilarity($vectors[0], $vectors[1]);
$unrelated = cosineSimilarity($vectors[0], $vectors[2]);

echo "Cosine similarity (cat/feline): " . number_format($similarity, 4) . "\n";
echo "Cosine similarity (cat/markets): " . number_format($unrelated, 4) . "\n";
/**
 * @param list<float> $left
 * @param list<float> $right
 */
function cosineSimilarity(array $left, array $right): float
{
    $dot = 0.0;
    $normLeft = 0.0;
    $normRight = 0.0;

    foreach ($left as $i => $value) {
        $other = $right[$i] ?? 0.0;
        $dot += $value * $other;
        $normLeft += $value * $value;
        $normRight += $other * $other;
    }

    if ($normLeft === 0.0 || $normRight === 0.0) {
        return 0.0;
    }

    return $dot / (sqrt($normLeft) * sqrt($normRight));
}
