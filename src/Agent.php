<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

use Exception;
use InvalidArgumentException;
use Tivins\LlmLib\Hooks\AfterLlmCallEvent;
use Tivins\LlmLib\Hooks\AfterToolCallEvent;
use Tivins\LlmLib\Hooks\AfterToolRoundEvent;
use Tivins\LlmLib\Hooks\AfterTurnEvent;
use Tivins\LlmLib\Hooks\BeforeLlmCallEvent;
use Tivins\LlmLib\Hooks\BeforeToolCallEvent;
use Tivins\LlmLib\Hooks\BeforeToolRoundEvent;
use Tivins\LlmLib\Hooks\BeforeTurnEvent;
use Tivins\LlmLib\Hooks\OnAssistantResponseEvent;
use Tivins\LlmLib\Hooks\OnMaxToolRoundsExceededEvent;

/** Runs a single agent turn: LLM calls, tool execution loops, and lifecycle hooks. */
class Agent
{
    public function __construct(
        public LLM $llm,
        public ToolRegistry $tools,
        public int $maxToolRounds = 10,
        public AgentHooks $hooks = new AgentHooks(),
    ) {}

    /**
     * @throws Exception
     */
    public function runTurn(Conversation $conversation, ChatCompletionOptions $options): AgentTurnResult
    {
        if ($options->tools !== null && $options->tools !== $this->tools) {
            throw new InvalidArgumentException(
                'ChatCompletionOptions::tools must be the same registry as Agent::tools, or omitted.',
            );
        }
        $options->tools = $this->tools;

        $this->hooks->dispatch(
            AgentHookEvent::BeforeTurn,
            new BeforeTurnEvent($conversation, $options),
        );

        $result = $this->runTurnInner($conversation, $options);

        $this->hooks->dispatch(
            AgentHookEvent::AfterTurn,
            new AfterTurnEvent($conversation, $options, $result),
        );

        return $result;
    }

    /**
     * @throws Exception
     */
    private function runTurnInner(Conversation $conversation, ChatCompletionOptions $options): AgentTurnResult
    {
        $response = $this->callLlm($conversation, $options, toolRound: 0);
        $toolRounds = 0;

        while ($response->hasToolCalls()) {
            if ($toolRounds >= $this->maxToolRounds) {
                $this->hooks->dispatch(
                    AgentHookEvent::OnMaxToolRoundsExceeded,
                    new OnMaxToolRoundsExceededEvent(
                        $conversation,
                        $options,
                        $toolRounds,
                        $this->maxToolRounds,
                    ),
                );

                return new AgentTurnResult(
                    null,
                    false,
                    "Max tool rounds ($this->maxToolRounds) exceeded.",
                    $toolRounds,
                );
            }

            $assistant = $response->assistantMessage();
            if ($assistant === null) {
                return new AgentTurnResult(
                    null,
                    false,
                    'Assistant message missing despite tool_calls.',
                    $toolRounds,
                );
            }

            $toolCalls = $assistant->toolCalls ?? [];

            $this->hooks->dispatch(
                AgentHookEvent::BeforeToolRound,
                new BeforeToolRoundEvent($conversation, $response, $assistant, $toolCalls, $toolRounds),
            );

            $conversation->addMessage(
                $response->toStoredMessage($options, $response->duration ?? 0.0) ?? $assistant,
            );

            $toolMessages = [];
            foreach ($toolCalls as $call) {
                $toolMessage = $this->executeToolCall($call, $toolRounds);
                $conversation->addMessage($toolMessage);
                $toolMessages[] = $toolMessage;
            }

            $this->hooks->dispatch(
                AgentHookEvent::AfterToolRound,
                new AfterToolRoundEvent($conversation, $toolMessages, $toolRounds),
            );

            $toolRounds++;
            $response = $this->callLlm($conversation, $options, $toolRounds);
        }

        $finishReason = $response->finishReason();
        if (
            $finishReason === 'stop'
            || ($finishReason === 'length' && !$response->hasToolCalls())
        ) {
            $stored = $response->toStoredMessage($options, $response->duration ?? 0.0);
            if ($stored !== null) {
                $stored = $this->applyAssistantResponseHooks($stored);
                $conversation->addMessage($stored);

                return new AgentTurnResult($stored, true, null, $toolRounds);
            }

            return new AgentTurnResult(
                null,
                false,
                'Assistant response could not be stored.',
                $toolRounds,
            );
        }

        $reason = $finishReason ?? 'unknown';

        return new AgentTurnResult(
            null,
            false,
            "Unexpected finish reason: $reason.",
            $toolRounds,
        );
    }

    /**
     * @throws Exception
     */
    private function callLlm(
        Conversation $conversation,
        ChatCompletionOptions $options,
        int $toolRound,
    ): ChatCompletionResponse {
        $this->hooks->dispatch(
            AgentHookEvent::BeforeLlmCall,
            new BeforeLlmCallEvent($conversation, $options, $toolRound),
        );

        $response = $this->llm->chatCompletion($conversation, $options);

        $this->hooks->dispatch(
            AgentHookEvent::AfterLlmCall,
            new AfterLlmCallEvent($conversation, $options, $toolRound, $response),
        );

        return $response;
    }

    private function applyAssistantResponseHooks(Message $stored): Message
    {
        $event = new OnAssistantResponseEvent($stored, $stored->content);
        $this->hooks->dispatch(AgentHookEvent::OnAssistantResponse, $event);
        if ($event->visibleContent === $stored->content) {
            return $stored;
        }

        return new Message(
            $stored->role,
            $event->visibleContent,
            $stored->reasoningContent,
            $stored->meta,
            $stored->toolCalls,
            $stored->toolCallId,
        );
    }

    private function executeToolCall(ToolCall $call, int $toolRound): Message
    {
        $event = new BeforeToolCallEvent($call, $toolRound);
        $this->hooks->dispatch(AgentHookEvent::BeforeToolCall, $event);

        if ($event->replacement !== null) {
            $toolMessage = $event->replacement;
        } else {
            $toolMessage = $this->tools->execute($call);
        }

        $this->hooks->dispatch(
            AgentHookEvent::AfterToolCall,
            new AfterToolCallEvent($call, $toolMessage, $toolRound),
        );

        return $toolMessage;
    }
}
