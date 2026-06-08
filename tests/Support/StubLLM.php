<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests\Support;

use RuntimeException;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\ChatCompletionResponse;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\LLM;

/** LLM double that returns pre-queued responses without HTTP. */
final class StubLLM extends LLM
{
    /** @var list<ChatCompletionResponse> */
    private array $responses = [];

    public function __construct()
    {
        parent::__construct('http://stub.test');
    }

    public function enqueue(ChatCompletionResponse ...$responses): void
    {
        foreach ($responses as $response) {
            $this->responses[] = $response;
        }
    }

    public function chatCompletion(Conversation $conversation, ChatCompletionOptions $options): ChatCompletionResponse
    {
        if ($this->responses === []) {
            throw new RuntimeException('StubLLM: no response queued.');
        }

        return array_shift($this->responses);
    }
}
