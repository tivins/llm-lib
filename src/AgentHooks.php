<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

final class AgentHooks
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function on(AgentHookEvent $event, callable $listener): self
    {
        $this->listeners[$event->value][] = $listener;

        return $this;
    }

    public function beforeTurn(callable $listener): self
    {
        return $this->on(AgentHookEvent::BeforeTurn, $listener);
    }

    public function afterTurn(callable $listener): self
    {
        return $this->on(AgentHookEvent::AfterTurn, $listener);
    }

    public function beforeLlmCall(callable $listener): self
    {
        return $this->on(AgentHookEvent::BeforeLlmCall, $listener);
    }

    public function afterLlmCall(callable $listener): self
    {
        return $this->on(AgentHookEvent::AfterLlmCall, $listener);
    }

    public function beforeToolRound(callable $listener): self
    {
        return $this->on(AgentHookEvent::BeforeToolRound, $listener);
    }

    public function afterToolRound(callable $listener): self
    {
        return $this->on(AgentHookEvent::AfterToolRound, $listener);
    }

    public function beforeToolCall(callable $listener): self
    {
        return $this->on(AgentHookEvent::BeforeToolCall, $listener);
    }

    public function afterToolCall(callable $listener): self
    {
        return $this->on(AgentHookEvent::AfterToolCall, $listener);
    }

    public function onMaxToolRoundsExceeded(callable $listener): self
    {
        return $this->on(AgentHookEvent::OnMaxToolRoundsExceeded, $listener);
    }

    public function onAssistantResponse(callable $listener): self
    {
        return $this->on(AgentHookEvent::OnAssistantResponse, $listener);
    }

    public function dispatch(AgentHookEvent $event, object $payload): void
    {
        foreach ($this->listeners[$event->value] ?? [] as $listener) {
            $listener($payload);
        }
    }

    public function has(AgentHookEvent $event): bool
    {
        return ($this->listeners[$event->value] ?? []) !== [];
    }
}
