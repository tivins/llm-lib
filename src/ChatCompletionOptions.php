<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** Request parameters for a chat completion (model, sampling, tools, format). */
class ChatCompletionOptions
{
    public function __construct(
        public ?string $model = null,
        public float $temperature = 0.7,
        public float $topP = 1.0,
        public int $n = 1,
        public ?ToolRegistry $tools = null,
        public ?string $toolChoice = null,
        public ?string $responseFormat = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toRequestArray(?string $defaultModel): array
    {
        $body = [
            'temperature' => $this->temperature,
            'top_p' => $this->topP,
            'n' => $this->n,
        ];

        $model = $this->model ?? $defaultModel;
        if ($model !== null) {
            $body['model'] = $model;
        }

        if ($this->tools !== null && $this->tools->all() !== []) {
            $body['tools'] = $this->tools->toRequestArray();
            if ($this->toolChoice !== null) {
                $body['tool_choice'] = $this->toolChoice;
            }
        }

        if ($this->responseFormat !== null) {
            $body['response_format'] = ['type' => $this->responseFormat];
        }

        return $body;
    }
}
