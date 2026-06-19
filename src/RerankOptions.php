<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** Request parameters for a rerank API call (model, top_n). */
class RerankOptions
{
    public function __construct(
        public ?string $model = null,
        public ?int $topN = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toRequestArray(?string $defaultModel): array
    {
        $body = [];

        $model = $this->model ?? $defaultModel;
        if ($model !== null) {
            $body['model'] = $model;
        }

        if ($this->topN !== null) {
            $body['top_n'] = $this->topN;
        }

        return $body;
    }
}
