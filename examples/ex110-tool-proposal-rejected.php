<?php

/**
 * ex110 — reject a proposed tool call in beforeToolCall (single tool round).
 * Compared with ex070, this shows approval gating: a sensitive tool is proposed
 * once, the hook returns a rejection payload via $replacement, and the model
 * answers without the real handler ever running.
 *
 * Uses StubLLM so the flow is deterministic and needs no running API server.
 */

declare(strict_types=1);

use Tivins\LlmLib\Agent;
use Tivins\LlmLib\AgentHooks;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\Hooks\BeforeToolCallEvent;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;
use Tivins\LlmLib\ToolCallRejection;
use Tivins\LlmLib\Tests\Support\ResponseFactory;
use Tivins\LlmLib\Tests\Support\StubLLM;
use Tivins\LlmLib\Tool;
use Tivins\LlmLib\ToolCall;
use Tivins\LlmLib\ToolRegistry;
use Tivins\LlmLib\ToolSchema;

require_once __DIR__ . '/../vendor/autoload.php';

$handlerRan = false;

$tools = new ToolRegistry(
    new Tool(
        new ToolSchema(
            name: 'write_file',
            description: 'Write text content to a file path.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                ],
                'required' => ['path', 'content'],
                'additionalProperties' => false,
            ],
        ),
        handler: static function (string $arguments) use (&$handlerRan): string {
            $handlerRan = true;

            return json_encode(['written' => true], JSON_THROW_ON_ERROR);
        },
    ),
);

$hooks = (new AgentHooks())
    ->beforeToolCall(static function (BeforeToolCallEvent $event): void {
        if ($event->call->name !== 'write_file') {
            return;
        }

        echo "[approval] proposed: {$event->call->name}\n";
        echo "[approval] arguments: {$event->call->arguments}\n";
        echo "[approval] user decision: rejected\n";

        $event->replacement = ToolCallRejection::userRejected($event->call);
    });

$llm = new StubLLM();
$llm->enqueue(
    ResponseFactory::assistantToolCalls([
        new ToolCall(
            'call_write_1',
            'write_file',
            json_encode(['path' => 'notes.txt', 'content' => 'hello'], JSON_THROW_ON_ERROR),
        ),
    ]),
    ResponseFactory::assistantText(
        'I could not write the file because you rejected the proposal. Tell me if you want a different path.',
    ),
);

$agent = new Agent(
    llm: $llm,
    tools: $tools,
    maxToolRounds: 2,
    hooks: $hooks,
);

$conversation = new Conversation(
    messages: [
        new Message(
            Role::System,
            'You are a coding assistant. Use write_file when the user asks to create a file.',
        ),
        new Message(
            Role::User,
            'Create notes.txt with the text hello.',
        ),
    ],
);

$result = $agent->runTurn($conversation, new ChatCompletionOptions());

if (!$result->success || $result->message === null) {
    fwrite(STDERR, 'Agent failed: ' . ($result->error ?? 'unknown error') . PHP_EOL);
    exit(1);
}

echo "\nFinal answer:\n";
echo trim($result->message->content) . PHP_EOL;
echo "\nTool rounds: {$result->toolRounds}\n";
echo 'Handler ran: ' . ($handlerRan ? 'yes' : 'no') . PHP_EOL;

if ($result->toolRounds !== 1) {
    fwrite(STDERR, "Expected 1 tool round, got {$result->toolRounds}\n");
    exit(1);
}

if ($handlerRan) {
    fwrite(STDERR, "Expected handler to be skipped after rejection\n");
    exit(1);
}
