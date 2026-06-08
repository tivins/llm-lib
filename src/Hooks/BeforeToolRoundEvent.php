<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Hooks;

use Tivins\LlmLib\ChatCompletionResponse;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\ToolCall;

/** Payload dispatched before the agent executes a round of tool calls. */
final readonly class BeforeToolRoundEvent
{
    /**
     * @param ToolCall[] $toolCalls
     */
    public function __construct(
        public Conversation           $conversation,
        public ChatCompletionResponse $response,
        public Message                $assistantMessage,
        public array                  $toolCalls,
        public int                    $toolRound,
    ) {}
}
