<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** Collection of registered tools and their handlers, exposed to the LLM and executed by the agent. */
class ToolRegistry
{
    /** @var array<string, ToolSchema> */
    private array $tools = [];

    /** @var array<string, callable(string): string> */
    private array $handlers = [];

    public function __construct(Tool ...$tools)
    {
        $this->registerTools(...$tools);
    }

    public function registerTools(Tool ...$tools): void
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->schema->name] = $tool->schema;
            $this->handlers[$tool->schema->name] = $tool->handler;
        }
    }

    /** @return ToolSchema[] */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toRequestArray(): array
    {
        return array_map(fn (ToolSchema $tool) => $tool->toArray(), $this->all());
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function execute(ToolCall $call): Message
    {
        $handler = $this->handlers[$call->name] ?? null;
        $content = $handler !== null
            ? $handler($call->arguments)
            : json_encode(['error' => "No handler for tool: $call->name"]);

        return new Message(Role::Tool, $content, toolCallId: $call->id);
    }

    /**
     * @param ToolCall[] $calls
     * @return Message[]
     */
    public function executeAll(array $calls): array
    {
        return array_map(fn (ToolCall $call) => $this->execute($call), $calls);
    }
}
