<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** Represents a function tool invocation requested by the assistant. */
class ToolCall
{
    public function __construct(
        public string $id,
        public string $name,
        public string $arguments,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['function']['name'],
            $data['function']['arguments'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => $this->arguments,
            ],
        ];
    }
}
