<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** Parsed response from an embeddings API call. */
class EmbeddingResponse
{
    /**
     * @param list<Embedding> $embeddings
     * @param array<string, mixed>|null $raw
     */
    public function __construct(
        public string $model,
        public Usage $usage,
        public array $embeddings,
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

    public function first(): ?Embedding
    {
        return $this->embeddings[0] ?? null;
    }

    /**
     * @return list<list<float>>
     */
    public function vectors(): array
    {
        return array_map(
            static fn (Embedding $embedding): array => $embedding->vector,
            $this->embeddings,
        );
    }
}
