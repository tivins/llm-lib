<?php

/**
 * ex130 — rerank documents for a query via llama.cpp POST /v1/rerank.
 *
 * Requires a llama.cpp server with a reranker model loaded, e.g. Qwen3-Reranker
 * started with --reranking --embedding --pooling rank on port 8082.
 *
 * Usage: php examples/ex130-rerank.php
 */

declare(strict_types=1);

use Tivins\LlmLib\LLM;
use Tivins\LlmLib\RerankOptions;

require_once __DIR__ . '/../vendor/autoload.php';

$llm = new LLM(
    endpoint: 'http://127.0.0.1:8082',
    defaultModel: 'Qwen3-Reranker-4B-Q4_K_M.gguf',
    timeoutSeconds: 120,
);

$query = 'What is a giant panda?';

$documents = [
    'The giant panda (Ailuropoda melanoleuca) is a bear endemic to China, known for its black-and-white coat and bamboo diet.',
    'Corporate earnings beat expectations and stock markets rallied in late trading.',
    'Pandas spend most of their day eating bamboo and resting in mountain forests.',
    'Quantum computing uses qubits that can exist in superposition.',
];

$options = new RerankOptions(topN: 3);

$response = $llm->rerank($query, $documents, $options);

echo "Model: {$response->model}\n";
echo "Query: {$query}\n";
echo "Tokens: {$response->usage->totalTokens}\n";
echo 'Duration: ' . number_format($response->duration ?? 0.0, 1) . " ms\n\n";
echo "Top documents:\n";

foreach ($response->rankedDocuments($documents) as $rank => $item) {
    $score = number_format($item['relevanceScore'], 4);
    echo ($rank + 1) . ". [{$score}] {$item['document']}\n";
}
