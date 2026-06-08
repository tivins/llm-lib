<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;
use Tivins\LlmLib\Tool;
use Tivins\LlmLib\ToolCall;
use Tivins\LlmLib\ToolRegistry;
use Tivins\LlmLib\ToolSchema;

final class ToolRegistryTest extends TestCase
{
    public function testExecuteReturnsToolMessageWithMatchingCallId(): void
    {
        $registry = new ToolRegistry(
            new Tool(
                new ToolSchema('add', 'Adds numbers', ['type' => 'object']),
                fn (string $args): string => 'result:' . $args,
            ),
        );

        $call = new ToolCall('call-1', 'add', '{"a":1,"b":2}');
        $message = $registry->execute($call);

        self::assertSame(Role::Tool, $message->role);
        self::assertSame('result:{"a":1,"b":2}', $message->content);
        self::assertSame('call-1', $message->toolCallId);
    }

    public function testUnknownToolReturnsJsonErrorContent(): void
    {
        $registry = new ToolRegistry();
        $message = $registry->execute(new ToolCall('x', 'missing', '{}'));

        self::assertSame(Role::Tool, $message->role);
        self::assertSame('{"error":"No handler for tool: missing"}', $message->content);
    }

    public function testToRequestArrayMatchesOpenAiToolShape(): void
    {
        $registry = new ToolRegistry(
            new Tool(
                new ToolSchema('search', 'Search the web', [
                    'type' => 'object',
                    'properties' => ['q' => ['type' => 'string']],
                ]),
                fn (): string => 'ok',
            ),
        );

        self::assertSame([[
            'type' => 'function',
            'function' => [
                'name' => 'search',
                'description' => 'Search the web',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['q' => ['type' => 'string']],
                ],
            ],
        ]], $registry->toRequestArray());
    }

    public function testRegisteringSameNameOverwritesPreviousHandler(): void
    {
        $registry = new ToolRegistry();
        $registry->registerTools(new Tool(
            new ToolSchema('dup', 'first', ['type' => 'object']),
            fn (): string => 'first',
        ));
        $registry->registerTools(new Tool(
            new ToolSchema('dup', 'second', ['type' => 'object']),
            fn (): string => 'second',
        ));

        $message = $registry->execute(new ToolCall('id', 'dup', '{}'));

        self::assertSame('second', $message->content);
        self::assertTrue($registry->has('dup'));
    }

    public function testExecuteAllPreservesOrder(): void
    {
        $registry = new ToolRegistry(
            new Tool(
                new ToolSchema('one', 'one', ['type' => 'object']),
                fn (): string => '1',
            ),
            new Tool(
                new ToolSchema('two', 'two', ['type' => 'object']),
                fn (): string => '2',
            ),
        );

        $messages = $registry->executeAll([
            new ToolCall('a', 'one', '{}'),
            new ToolCall('b', 'two', '{}'),
        ]);

        self::assertSame(['1', '2'], array_map(fn (Message $m) => $m->content, $messages));
    }
}
