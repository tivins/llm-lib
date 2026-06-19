<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** Request parameters for an embeddings API call (model, encoding, dimensions). */
class EmbeddingOptions
{
    public function __construct(
        public ?string $model = null,
        public ?string $encodingFormat = null,
        public ?int $dimensions = null,
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

        if ($this->encodingFormat !== null) {
            $body['encoding_format'] = $this->encodingFormat;
        }

        if ($this->dimensions !== null) {
            $body['dimensions'] = $this->dimensions;
        }

        return $body;
    }
}
