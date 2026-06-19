<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** A single rerank score for one document index. */
class RerankResult
{
    public function __construct(
        public int $index,
        public float $relevanceScore,
    ) {
    }
}
