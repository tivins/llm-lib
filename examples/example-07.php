<?php

/**
 * Example 7: demonstrates advanced Agent lifecycle hooks.
 * Compared with Example 6, this adds turn-level hooks, tool-round hooks, mocked
 * tool execution via BeforeToolCall::$replacement, and response rewriting via
 * OnAssistantResponse::$visibleContent.
 */

declare(strict_types=1);

use Tivins\LlmLib\Agent;
use Tivins\LlmLib\AgentHooks;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\Hooks\AfterToolRoundEvent;
use Tivins\LlmLib\Hooks\AfterTurnEvent;
use Tivins\LlmLib\Hooks\BeforeLlmCallEvent;
use Tivins\LlmLib\Hooks\BeforeToolCallEvent;
use Tivins\LlmLib\Hooks\BeforeToolRoundEvent;
use Tivins\LlmLib\Hooks\BeforeTurnEvent;
use Tivins\LlmLib\Hooks\OnAssistantResponseEvent;
use Tivins\LlmLib\LLM;
use Tivins\LlmLib\Logger;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;
use Tivins\LlmLib\Tool;
use Tivins\LlmLib\ToolRegistry;
use Tivins\LlmLib\ToolSchema;

require_once __DIR__ . '/../vendor/autoload.php';

$logDir = __DIR__ . '/../var/log';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$llm = new LLM(
    endpoint: 'http://127.0.0.1:8080',
    timeoutSeconds: 300,
);

$inventory = [
    'SKU-100' => ['name' => 'Wireless mouse', 'stock' => 42, 'warehouse' => 'Paris'],
    'SKU-200' => ['name' => 'USB-C hub', 'stock' => 3, 'warehouse' => 'Lyon'],
];

$tools = new ToolRegistry(
    new Tool(
        new ToolSchema(
            name: 'lookup_inventory',
            description: 'Look up product stock by SKU.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'sku' => [
                        'type' => 'string',
                        'description' => 'Product SKU, for example SKU-100.',
                    ],
                ],
                'required' => ['sku'],
                'additionalProperties' => false,
            ],
        ),
        handler: static function (string $arguments) use ($inventory): string {
            $payload = json_decode($arguments, true);
            $sku = is_array($payload) ? ($payload['sku'] ?? '') : '';

            if (!is_string($sku) || $sku === '') {
                return json_encode(['error' => 'Missing sku'], JSON_THROW_ON_ERROR);
            }

            return json_encode(
                $inventory[$sku] ?? ['error' => 'SKU not found', 'sku' => $sku],
                JSON_THROW_ON_ERROR,
            );
        },
    ),
);

$hooks = (new AgentHooks())
    ->beforeTurn(static function (BeforeTurnEvent $event): void {
        $userMessages = array_filter(
            $event->conversation->messages,
            static fn (Message $message): bool => $message->role === Role::User,
        );
        echo '[hook] beforeTurn, user messages so far: ' . count($userMessages) . PHP_EOL;
    })
    ->beforeLlmCall(static function (BeforeLlmCallEvent $event): void {
        echo "[hook] beforeLlmCall, tool round {$event->toolRound}\n";
    })
    ->beforeToolRound(static function (BeforeToolRoundEvent $event): void {
        $toolNames = array_map(static fn ($call) => $call->name, $event->toolCalls);
        echo '[hook] beforeToolRound: ' . implode(', ', $toolNames) . PHP_EOL;
    })
    ->beforeToolCall(static function (BeforeToolCallEvent $event): void {
        if ($event->call->name !== 'lookup_inventory') {
            return;
        }

        // Simulate an external inventory API outage without changing the tool schema.
        $event->replacement = new Message(
            Role::Tool,
            json_encode([
                'sku' => json_decode($event->call->arguments, true)['sku'] ?? 'unknown',
                'name' => 'Wireless mouse',
                'stock' => 999,
                'warehouse' => 'Mock warehouse',
                'source' => 'mocked_by_hook',
            ], JSON_THROW_ON_ERROR),
            toolCallId: $event->call->id,
        );
        echo "[hook] beforeToolCall: mocked lookup_inventory ({$event->call->id})\n";
    })
    ->afterToolRound(static function (AfterToolRoundEvent $event): void {
        echo '[hook] afterToolRound, tool messages appended: ' . count($event->toolMessages) . PHP_EOL;
    })
    ->onAssistantResponse(static function (OnAssistantResponseEvent $event): void {
        $event->setVisibleContent('[Support Bot] ' . trim($event->rawContent));
        echo "[hook] onAssistantResponse: prefixed visible content\n";
    })
    ->afterTurn(static function (AfterTurnEvent $event): void {
        $status = $event->result->success ? 'success' : 'failure';
        echo "[hook] afterTurn: {$status}, tool rounds {$event->result->toolRounds}\n";
    });

$agent = new Agent(
    llm: $llm,
    tools: $tools,
    maxToolRounds: 3,
    hooks: $hooks,
);

$conversation = new Conversation(
    messages: [
        new Message(
            Role::System,
            implode(' ', [
                'You are a stock assistant.',
                'When the user gives a SKU, call lookup_inventory before answering.',
                'Mention stock level and warehouse in one short paragraph.',
            ]),
        ),
        new Message(
            Role::User,
            'Can we ship 10 units of SKU-100 today?',
        ),
    ],
    logger: new Logger($logDir . '/ex7_' . date('Ymd_His') . '.json'),
);

$result = $agent->runTurn(
    $conversation,
    new ChatCompletionOptions(temperature: 0.2, topP: 0.9, toolChoice: 'auto'),
);

if (!$result->success || $result->message === null) {
    fwrite(STDERR, 'Agent failed: ' . ($result->error ?? 'unknown error') . PHP_EOL);
    exit(1);
}

echo "\nFinal visible answer:\n";
echo trim($result->message->content) . PHP_EOL;
