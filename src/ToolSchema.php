<?php
declare(strict_types=1);

namespace Tivins\LlmLib;

/** JSON-schema description of a callable tool sent to the LLM. */
class ToolSchema
{
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters,
            ],
        ];
    }
}
