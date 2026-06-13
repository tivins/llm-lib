<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

/** A single chat message with role, content, metadata, and optional tool calls. */
class Message implements JsonSerializable
{
    /**
     * @param array<string, mixed> $meta
     * @param ToolCall[]|null $toolCalls
     */
    public function __construct(
        public Role $role,
        public string $content,
        public ?string $reasoningContent = null,
        public array $meta = [],
        public ?array $toolCalls = null,
        public ?string $toolCallId = null,
    ) {
    }

    public static function withCreatedAt(
        Role               $role,
        string             $content,
        ?string            $reasoningContent = null,
        ?DateTimeImmutable $at = null,
    ): self {
        $at ??= new DateTimeImmutable();

        return new self($role, $content, $reasoningContent, [
            'created_at' => $at->format(DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'role' => $this->role->value,
            'content' => $this->content,
        ];
        if ($this->reasoningContent !== null) {
            $payload['reasoning_content'] = $this->reasoningContent;
        }
        if ($this->toolCalls !== null && $this->toolCalls !== []) {
            $payload['tool_calls'] = array_map(
                fn (ToolCall $call) => $call->toArray(),
                $this->toolCalls,
            );
        }
        if ($this->toolCallId !== null) {
            $payload['tool_call_id'] = $this->toolCallId;
        }
        if ($this->meta !== []) {
            $payload['meta'] = $this->meta;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function toChatCompletionArray(): array
    {
        $content = $this->content;
        if ($this->role === Role::Assistant && str_contains($content, '<|channel|>')) {
            $content = HarmonyContent::parse($content)['content'];
        }

        $payload = [
            'role' => $this->role->value,
            'content' => $content === '' ? null : $content,
        ];
        // reasoning_content is intentionally omitted here: it is internal chain-of-thought
        // produced by the model and must not be re-injected into subsequent requests
        // (it would bloat the context window without benefit). It is preserved in toArray()
        // for logging purposes only.
        if ($this->toolCalls !== null && $this->toolCalls !== []) {
            $payload['tool_calls'] = array_map(
                fn (ToolCall $call) => $call->toArray(),
                $this->toolCalls,
            );
        }
        if ($this->toolCallId !== null) {
            $payload['tool_call_id'] = $this->toolCallId;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
