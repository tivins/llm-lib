<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** Parsed response from a rerank API call. */
class RerankResponse
{
    /**
     * @param list<RerankResult> $results
     * @param array<string, mixed>|null $raw
     */
    public function __construct(
        public string $model,
        public Usage $usage,
        public array $results,
        private ?array $raw = null,
        public ?float $duration = null,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function raw(): ?array
    {
        return $this->raw;
    }

    /**
     * Results sorted by relevance score, highest first.
     *
     * @return list<RerankResult>
     */
    public function sortedResults(): array
    {
        $sorted = $this->results;
        usort(
            $sorted,
            static fn (RerankResult $left, RerankResult $right): int => $right->relevanceScore <=> $left->relevanceScore,
        );

        return $sorted;
    }

    /**
     * Map ranked results back to the original document strings.
     *
     * @param list<string> $documents
     *
     * @return list<array{index: int, document: string, relevanceScore: float}>
     */
    public function rankedDocuments(array $documents): array
    {
        $ranked = [];
        foreach ($this->sortedResults() as $result) {
            if (!array_key_exists($result->index, $documents)) {
                continue;
            }

            $ranked[] = [
                'index' => $result->index,
                'document' => $documents[$result->index],
                'relevanceScore' => $result->relevanceScore,
            ];
        }

        return $ranked;
    }
}
