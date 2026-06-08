<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\Agent;
use Tivins\LlmLib\AgentHooks;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\Hooks\BeforeToolCallEvent;
use Tivins\LlmLib\Hooks\OnAssistantResponseEvent;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;
use Tivins\LlmLib\Tests\Support\ResponseFactory;
use Tivins\LlmLib\Tests\Support\StubLLM;
use Tivins\LlmLib\Tool;
use Tivins\LlmLib\ToolCall;
use Tivins\LlmLib\ToolRegistry;
use Tivins\LlmLib\ToolSchema;

final class AgentTest extends TestCase
{
    public function testSimpleStopResponseStoresAssistantMessage(): void
    {
        $llm = new StubLLM();
        $llm->enqueue(ResponseFactory::assistantText('Hello!'));

        $tools = new ToolRegistry();
        $agent = new Agent($llm, $tools);
        $conversation = new Conversation();
        $options = new ChatCompletionOptions(temperature: 0.3);

        $result = $agent->runTurn($conversation, $options);

        self::assertTrue($result->success);
        self::assertSame('Hello!', $result->message?->content);
        self::assertSame(0, $result->toolRounds);
        self::assertCount(1, $conversation->messages);
        self::assertSame(0.3, $conversation->messages[0]->meta['temperature']);
        self::assertSame($tools, $options->tools);
    }

    public function testToolRoundExecutesHandlerAndContinuesUntilStop(): void
    {
        $llm = new StubLLM();
        $llm->enqueue(
            ResponseFactory::assistantToolCalls([
                new ToolCall('call-1', 'echo', '{"text":"hi"}'),
            ]),
            ResponseFactory::assistantText('Done'),
        );

        $tools = new ToolRegistry(
            new Tool(
                new ToolSchema('echo', 'Echo', ['type' => 'object']),
                fn (string $args): string => 'echoed:' . $args,
            ),
        );
        $agent = new Agent($llm, $tools);
        $conversation = new Conversation();

        $result = $agent->runTurn($conversation, new ChatCompletionOptions());

        self::assertTrue($result->success);
        self::assertSame('Done', $result->message?->content);
        self::assertSame(1, $result->toolRounds);
        self::assertCount(3, $conversation->messages);
        self::assertSame(Role::Assistant, $conversation->messages[0]->role);
        self::assertNotEmpty($conversation->messages[0]->toolCalls);
        self::assertSame(Role::Tool, $conversation->messages[1]->role);
        self::assertSame('echoed:{"text":"hi"}', $conversation->messages[1]->content);
        self::assertSame('call-1', $conversation->messages[1]->toolCallId);
        self::assertSame('Done', $conversation->messages[2]->content);
    }

    public function testMaxToolRoundsExceededReturnsFailure(): void
    {
        $llm = new StubLLM();
        $toolCall = new ToolCall('c', 'loop', '{}');
        $llm->enqueue(
            ResponseFactory::assistantToolCalls([$toolCall]),
            ResponseFactory::assistantToolCalls([$toolCall]),
        );

        $tools = new ToolRegistry(
            new Tool(
                new ToolSchema('loop', 'loop', ['type' => 'object']),
                fn (): string => 'again',
            ),
        );
        $hooks = new AgentHooks();
        $exceeded = false;
        $hooks->onMaxToolRoundsExceeded(function () use (&$exceeded): void {
            $exceeded = true;
        });

        $agent = new Agent($llm, $tools, maxToolRounds: 1, hooks: $hooks);
        $result = $agent->runTurn(new Conversation(), new ChatCompletionOptions());

        self::assertFalse($result->success);
        self::assertStringContainsString('Max tool rounds', (string) $result->error);
        self::assertSame(1, $result->toolRounds);
        self::assertTrue($exceeded);
    }

    public function testMismatchedToolsRegistryThrows(): void
    {
        $agent = new Agent(new StubLLM(), new ToolRegistry());
        $otherRegistry = new ToolRegistry();

        $this->expectException(InvalidArgumentException::class);
        $agent->runTurn(new Conversation(), new ChatCompletionOptions(tools: $otherRegistry));
    }

    public function testBeforeToolCallReplacementSkipsHandler(): void
    {
        $llm = new StubLLM();
        $llm->enqueue(
            ResponseFactory::assistantToolCalls([new ToolCall('id', 'real', '{}')]),
            ResponseFactory::assistantText('ok'),
        );

        $tools = new ToolRegistry(
            new Tool(
                new ToolSchema('real', 'real', ['type' => 'object']),
                fn (): string => 'should-not-run',
            ),
        );
        $hooks = new AgentHooks();
        $hooks->beforeToolCall(function (BeforeToolCallEvent $event): void {
            $event->replacement = new Message(Role::Tool, 'mocked', toolCallId: $event->call->id);
        });

        $conversation = new Conversation();
        $agent = new Agent($llm, $tools, hooks: $hooks);
        $agent->runTurn($conversation, new ChatCompletionOptions());

        self::assertSame('mocked', $conversation->messages[1]->content);
    }

    public function testOnAssistantResponseCanAlterVisibleContent(): void
    {
        $llm = new StubLLM();
        $llm->enqueue(ResponseFactory::assistantText('raw'));

        $hooks = new AgentHooks();
        $hooks->onAssistantResponse(function (OnAssistantResponseEvent $event): void {
            $event->setVisibleContent('sanitized');
        });

        $agent = new Agent($llm, new ToolRegistry(), hooks: $hooks);
        $conversation = new Conversation();
        $result = $agent->runTurn($conversation, new ChatCompletionOptions());

        self::assertTrue($result->success);
        self::assertSame('sanitized', $result->message?->content);
        self::assertSame('sanitized', $conversation->messages[0]->content);
    }

    public function testLengthFinishReasonWithoutToolCallsIsSuccess(): void
    {
        $llm = new StubLLM();
        $llm->enqueue(ResponseFactory::assistantText('truncated', 'length'));

        $agent = new Agent($llm, new ToolRegistry());
        $result = $agent->runTurn(new Conversation(), new ChatCompletionOptions());

        self::assertTrue($result->success);
        self::assertSame('truncated', $result->message?->content);
    }

    public function testHookOrderForSimpleTurn(): void
    {
        $llm = new StubLLM();
        $llm->enqueue(ResponseFactory::assistantText('ok'));

        $hooks = new AgentHooks();
        $order = [];
        $hooks->beforeTurn(function () use (&$order): void {
            $order[] = 'beforeTurn';
        });
        $hooks->beforeLlmCall(function () use (&$order): void {
            $order[] = 'beforeLlmCall';
        });
        $hooks->afterLlmCall(function () use (&$order): void {
            $order[] = 'afterLlmCall';
        });
        $hooks->onAssistantResponse(function () use (&$order): void {
            $order[] = 'onAssistantResponse';
        });
        $hooks->afterTurn(function () use (&$order): void {
            $order[] = 'afterTurn';
        });

        $agent = new Agent($llm, new ToolRegistry(), hooks: $hooks);
        $agent->runTurn(new Conversation(), new ChatCompletionOptions());

        self::assertSame([
            'beforeTurn',
            'beforeLlmCall',
            'afterLlmCall',
            'onAssistantResponse',
            'afterTurn',
        ], $order);
    }

    public function testUnexpectedFinishReasonReturnsFailure(): void
    {
        $llm = new StubLLM();
        $llm->enqueue(ResponseFactory::assistantText('?', 'content_filter'));

        $agent = new Agent($llm, new ToolRegistry());
        $result = $agent->runTurn(new Conversation(), new ChatCompletionOptions());

        self::assertFalse($result->success);
        self::assertStringContainsString('content_filter', (string) $result->error);
    }
}
