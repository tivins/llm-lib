<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Hooks;

use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\ChatCompletionResponse;
use Tivins\LlmLib\Conversation;

/** Payload dispatched after each LLM API call within a turn. */
final readonly class AfterLlmCallEvent
{
    public function __construct(
        public Conversation           $conversation,
        public ChatCompletionOptions  $options,
        public int                    $toolRound,
        public ChatCompletionResponse $response,
    ) {
    }
}
