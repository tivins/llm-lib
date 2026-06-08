<?php
declare(strict_types=1);

namespace Tivins\LlmLib;

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
