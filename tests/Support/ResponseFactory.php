<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests\Support;

use Tivins\LlmLib\ChatCompletionResponse;
use Tivins\LlmLib\Choice;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;
use Tivins\LlmLib\ToolCall;
use Tivins\LlmLib\Usage;

/** Builds ChatCompletionResponse instances for agent tests. */
final class ResponseFactory
{
    public static function assistantText(string $content, string $finishReason = 'stop'): ChatCompletionResponse
    {
        return self::response(
            new Message(Role::Assistant, $content),
            $finishReason,
        );
    }

    /**
     * @param ToolCall[] $toolCalls
     */
    public static function assistantToolCalls(array $toolCalls, string $content = ''): ChatCompletionResponse
    {
        return self::response(
            new Message(Role::Assistant, $content, toolCalls: $toolCalls),
            'tool_calls',
        );
    }

    private static function response(Message $message, string $finishReason): ChatCompletionResponse
    {
        return new ChatCompletionResponse(
            'test-model',
            new Usage(10, 5, 15),
            [new Choice(0, $message, $finishReason)],
            duration: 42.5,
        );
    }
}
