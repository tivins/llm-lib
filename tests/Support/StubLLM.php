<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests\Support;

use RuntimeException;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\ChatCompletionResponse;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\EmbeddingOptions;
use Tivins\LlmLib\EmbeddingResponse;
use Tivins\LlmLib\LLM;

/** LLM double that returns pre-queued responses without HTTP. */
final class StubLLM extends LLM
{
    /** @var list<ChatCompletionResponse> */
    private array $responses = [];

    /** @var list<list<int>> */
    private array $tokenizeResponses = [];

    /** @var list<EmbeddingResponse> */
    private array $embeddingResponses = [];

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

    /** @param list<int> $tokens */
    public function enqueueTokens(array $tokens): void
    {
        $this->tokenizeResponses[] = $tokens;
    }

    public function enqueueEmbeddings(EmbeddingResponse ...$responses): void
    {
        foreach ($responses as $response) {
            $this->embeddingResponses[] = $response;
        }
    }

    public function chatCompletion(Conversation $conversation, ChatCompletionOptions $options): ChatCompletionResponse
    {
        if ($this->responses === []) {
            throw new RuntimeException('StubLLM: no response queued.');
        }

        return array_shift($this->responses);
    }

    public function tokenize(string $text): array
    {
        if ($this->tokenizeResponses === []) {
            throw new RuntimeException('StubLLM: no tokenize response queued.');
        }

        return array_shift($this->tokenizeResponses);
    }

    public function embeddings(string|array $input, EmbeddingOptions $options = new EmbeddingOptions()): EmbeddingResponse
    {
        if ($this->embeddingResponses === []) {
            throw new RuntimeException('StubLLM: no embedding response queued.');
        }

        return array_shift($this->embeddingResponses);
    }
}
