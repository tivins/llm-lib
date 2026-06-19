<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** A single embedding vector returned by the embeddings API. */
class Embedding
{
    /**
     * @param list<float> $vector
     */
    public function __construct(
        public int $index,
        public array $vector,
    ) {
    }
}
