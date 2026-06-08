<?php
declare(strict_types=1);

namespace Tivins\LlmLib;

use DateTimeImmutable;
use DateTimeInterface;

/** Parsed response from a chat completion API call. */
class ChatCompletionResponse
{
    /**
     * @param Choice[] $choices
     */
    public function __construct(
        public string $model,
        public Usage $usage,
        public array $choices,
        private ?array $raw = null,
        public ?float $duration = null,
    ) {}

    public function raw(): ?array
    {
        return $this->raw;
    }

    public function firstChoice(): ?Choice
    {
        return $this->choices[0] ?? null;
    }

    public function finishReason(): ?string
    {
        return $this->firstChoice()?->finishReason;
    }

    public function assistantMessage(): ?Message
    {
        $message = $this->firstChoice()?->message;
        if ($message?->role === Role::Assistant) {
            return $message;
        }

        return null;
    }

    public function hasToolCalls(): bool
    {
        $calls = $this->assistantMessage()?->toolCalls;

        return $calls !== null && $calls !== [];
    }

    public function toStoredMessage(
        ChatCompletionOptions $options,
        float                 $elapsedMs,
        ?DateTimeImmutable    $at = null,
    ): ?Message {
        $assistant = $this->assistantMessage();
        if ($assistant === null) {
            return null;
        }

        $at ??= new DateTimeImmutable();
        $meta = [
            'created_at' => $at->format(DateTimeInterface::ATOM),
            'time_ms' => $elapsedMs,
            'model' => $this->model,
            'temperature' => $options->temperature,
            'usage' => $this->usage->toArray(),
        ];
        $finishReason = $this->finishReason();
        if ($finishReason !== null) {
            $meta['finish_reason'] = $finishReason;
        }

        return new Message(
            $assistant->role,
            $assistant->content,
            $assistant->reasoningContent,
            $meta,
            $assistant->toolCalls,
        );
    }
}
