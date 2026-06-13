<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;
use Tivins\LlmLib\ToolCall;

final class MessageTest extends TestCase
{
    public function testToArrayIncludesMetaAndReasoningContent(): void
    {
        $message = new Message(
            Role::Assistant,
            'answer',
            'internal reasoning',
            ['temperature' => 0.5, 'model' => 'gpt-test'],
            [new ToolCall('call-1', 'search', '{"q":"php"}')],
        );

        self::assertSame([
            'role' => 'assistant',
            'content' => 'answer',
            'reasoning_content' => 'internal reasoning',
            'tool_calls' => [[
                'id' => 'call-1',
                'type' => 'function',
                'function' => ['name' => 'search', 'arguments' => '{"q":"php"}'],
            ]],
            'meta' => ['temperature' => 0.5, 'model' => 'gpt-test'],
        ], $message->toArray());
    }

    public function testToChatCompletionArrayOmitsReasoningContentAndMeta(): void
    {
        $message = new Message(
            Role::Assistant,
            'answer',
            'internal reasoning',
            ['temperature' => 0.5],
            [new ToolCall('call-1', 'search', '{}')],
        );

        self::assertSame([
            'role' => 'assistant',
            'content' => 'answer',
            'tool_calls' => [[
                'id' => 'call-1',
                'type' => 'function',
                'function' => ['name' => 'search', 'arguments' => '{}'],
            ]],
        ], $message->toChatCompletionArray());
    }

    public function testEmptyContentBecomesNullInChatCompletionPayload(): void
    {
        $message = new Message(Role::Assistant, '');

        self::assertSame([
            'role' => 'assistant',
            'content' => null,
        ], $message->toChatCompletionArray());

        self::assertSame('assistant', $message->toArray()['role']);
        self::assertSame('', $message->toArray()['content']);
    }

    public function testToolMessageIncludesToolCallIdInBothSerializers(): void
    {
        $message = new Message(Role::Tool, '{"ok":true}', toolCallId: 'call-99');

        self::assertSame('call-99', $message->toArray()['tool_call_id']);
        self::assertSame('call-99', $message->toChatCompletionArray()['tool_call_id']);
    }

    public function testWithCreatedAtStoresAtomTimestampInMeta(): void
    {
        $at = new DateTimeImmutable('2026-06-09T12:00:00+00:00');
        $message = Message::withCreatedAt(Role::User, 'hello', at: $at);

        self::assertSame('2026-06-09T12:00:00+00:00', $message->meta['created_at']);
        self::assertArrayNotHasKey('meta', $message->toChatCompletionArray());
    }

    public function testJsonSerializeMatchesToArray(): void
    {
        $message = new Message(Role::User, 'hi', meta: ['source' => 'test']);

        self::assertSame($message->toArray(), $message->jsonSerialize());
    }

    public function testEmptyToolCallsAreOmittedFromPayloads(): void
    {
        $message = new Message(Role::Assistant, 'ok', toolCalls: []);

        self::assertArrayNotHasKey('tool_calls', $message->toArray());
        self::assertArrayNotHasKey('tool_calls', $message->toChatCompletionArray());
    }

    public function testToChatCompletionArrayStripsHarmonyChannelMarkersFromAssistantContent(): void
    {
        $message = new Message(
            Role::Assistant,
            '<|channel|>analysis<|message|>internal thought<|end|>'
            . '<|start|>assistant<|channel|>final<|message|>User-facing answer<|return|>',
        );

        self::assertSame([
            'role' => 'assistant',
            'content' => 'User-facing answer',
        ], $message->toChatCompletionArray());
    }
}
