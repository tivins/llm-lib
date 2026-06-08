<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\AgentHookEvent;
use Tivins\LlmLib\AgentHooks;
use Tivins\LlmLib\Hooks\BeforeTurnEvent;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;

final class AgentHooksTest extends TestCase
{
    public function testDispatchInvokesRegisteredListenersInOrder(): void
    {
        $hooks = new AgentHooks();
        $order = [];

        $hooks->beforeTurn(function () use (&$order): void {
            $order[] = 'first';
        });
        $hooks->beforeTurn(function () use (&$order): void {
            $order[] = 'second';
        });

        $hooks->dispatch(
            AgentHookEvent::BeforeTurn,
            new BeforeTurnEvent(new Conversation(), new ChatCompletionOptions()),
        );

        self::assertSame(['first', 'second'], $order);
    }

    public function testHasReportsRegisteredEvents(): void
    {
        $hooks = new AgentHooks();
        $hooks->afterLlmCall(static fn () => null);

        self::assertTrue($hooks->has(AgentHookEvent::AfterLlmCall));
        self::assertFalse($hooks->has(AgentHookEvent::BeforeTurn));
    }

    public function testFluentRegistrationReturnsSameInstance(): void
    {
        $hooks = new AgentHooks();

        self::assertSame($hooks, $hooks->beforeToolCall(static fn () => null));
    }
}
