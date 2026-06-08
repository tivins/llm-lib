<?php
declare(strict_types=1);

namespace Tivins\LlmLib;


use JsonSerializable;

/** Holds the ordered list of messages in an LLM chat session. */
class Conversation implements JsonSerializable
{
    /**
     * @param Message[] $messages
     */
    public function __construct(
        public array $messages = [],
        public ?Logger $logger = null,
    ) {}

    public function addMessage(Message $message): void
    {
        $this->messages[] = $message;
        $this->logger?->saveConversation($this);
    }

    public function toChatCompletionArray(): array
    {
        return array_map(
            fn (Message $message) => $message->toChatCompletionArray(),
            $this->messages,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'messages' => array_map(
                fn (Message $message) => $message->toArray(),
                $this->messages,
            ),
        ];
    }
}
