<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\ChatCompletionResponse;
use Tivins\LlmLib\Choice;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;
use Tivins\LlmLib\ToolCall;
use Tivins\LlmLib\Usage;

final class ChatCompletionResponseTest extends TestCase
{
    public function testHasToolCallsRequiresNonEmptyAssistantToolCalls(): void
    {
        $withCalls = $this->response(
            new Message(Role::Assistant, '', toolCalls: [new ToolCall('1', 'x', '{}')]),
            'tool_calls',
        );
        $withoutCalls = $this->response(new Message(Role::Assistant, 'ok'), 'stop');
        $userChoice = $this->response(new Message(Role::User, 'hi'), 'stop');

        self::assertTrue($withCalls->hasToolCalls());
        self::assertFalse($withoutCalls->hasToolCalls());
        self::assertFalse($userChoice->hasToolCalls());
        self::assertNull($userChoice->assistantMessage());
    }

    public function testToStoredMessageBuildsMetadataFromResponse(): void
    {
        $at = new DateTimeImmutable('2026-06-09T10:00:00+00:00');
        $response = $this->response(
            new Message(Role::Assistant, 'hello', 'reasoning'),
            'stop',
            duration: 99.5,
        );

        $stored = $response->toStoredMessage($at);

        self::assertNotNull($stored);
        self::assertSame('hello', $stored->content);
        self::assertSame('reasoning', $stored->reasoningContent);
        self::assertSame([
            'created_at' => '2026-06-09T10:00:00+00:00',
            'time_ms' => 99.5,
            'model' => 'model-x',
            'usage' => [
                'prompt_tokens' => 3,
                'completion_tokens' => 7,
                'total_tokens' => 10,
            ],
            'finish_reason' => 'stop',
        ], $stored->meta);
    }

    public function testToStoredMessageReturnsNullWhenFirstChoiceIsNotAssistant(): void
    {
        $response = $this->response(new Message(Role::Tool, 'data'), 'stop');

        self::assertNull($response->toStoredMessage());
    }

    private function response(Message $message, string $finishReason, ?float $duration = null): ChatCompletionResponse
    {
        return new ChatCompletionResponse(
            'model-x',
            new Usage(3, 7, 10),
            [new Choice(0, $message, $finishReason)],
            duration: $duration,
        );
    }
}
