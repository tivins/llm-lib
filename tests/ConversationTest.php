<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;

final class ConversationTest extends TestCase
{
    public function testAddMessageAppendsInOrder(): void
    {
        $conversation = new Conversation();
        $conversation->addMessage(new Message(Role::User, 'first'));
        $conversation->addMessage(new Message(Role::Assistant, 'second'));

        self::assertCount(2, $conversation->messages);
        self::assertSame('first', $conversation->messages[0]->content);
        self::assertSame('second', $conversation->messages[1]->content);
    }

    public function testToChatCompletionArrayMapsEachMessage(): void
    {
        $conversation = new Conversation([
            new Message(Role::System, 'rules'),
            new Message(Role::User, 'question'),
        ]);

        self::assertSame([
            ['role' => 'system', 'content' => 'rules'],
            ['role' => 'user', 'content' => 'question'],
        ], $conversation->toChatCompletionArray());
    }

    public function testJsonSerializeUsesFullMessageRepresentation(): void
    {
        $conversation = new Conversation([
            new Message(Role::Assistant, 'ok', reasoningContent: 'thought', meta: ['model' => 'x']),
        ]);

        $serialized = $conversation->jsonSerialize();

        self::assertSame('thought', $serialized['messages'][0]['reasoning_content']);
        self::assertSame(['model' => 'x'], $serialized['messages'][0]['meta']);
    }
}
