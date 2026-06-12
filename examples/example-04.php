<?php

/**
 * Example 4: lets the Agent call one deterministic PHP tool before answering.
 * Compared with Example 3, this adds a ToolRegistry, a JSON schema, a tool
 * handler, and one support scenario where the model must look up order data.
 */

declare(strict_types=1);

use Tivins\LlmLib\Agent;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;
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

$orders = [
    'FR-2026-0042' => [
        'customer' => 'Camille Martin',
        'status' => 'delayed',
        'carrier' => 'Colissimo',
        'last_event' => 'Sorting center delay in Lyon',
        'estimated_delivery' => '2026-06-12',
    ],
    'FR-2026-0081' => [
        'customer' => 'Nadia Benali',
        'status' => 'delivered',
        'carrier' => 'Chronopost',
        'last_event' => 'Delivered to pickup point',
        'estimated_delivery' => '2026-06-08',
    ],
];

$tools = new ToolRegistry(
    new Tool(
        new ToolSchema(
            name: 'lookup_order',
            description: 'Look up a customer order by its public order reference.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'order_reference' => [
                        'type' => 'string',
                        'description' => 'The order reference, for example FR-2026-0042.',
                    ],
                ],
                'required' => ['order_reference'],
                'additionalProperties' => false,
            ],
        ),
        handler: static function (string $arguments) use ($orders): string {
            $payload = json_decode($arguments, true);
            $reference = is_array($payload) ? ($payload['order_reference'] ?? '') : '';

            if (!is_string($reference) || $reference === '') {
                return json_encode(['error' => 'Missing order_reference'], JSON_THROW_ON_ERROR);
            }

            return json_encode(
                $orders[$reference] ?? ['error' => 'Order not found', 'order_reference' => $reference],
                JSON_THROW_ON_ERROR,
            );
        },
    ),
);

$agent = new Agent($llm, $tools, maxToolRounds: 2);

$conversation = new Conversation(
    messages: [
        new Message(
            Role::System,
            implode(' ', [
                'You are a customer-support assistant.',
                'When the user gives an order reference, call lookup_order before answering.',
                'Answer with the useful facts and a customer-ready sentence.',
            ]),
        ),
        new Message(
            Role::User,
            'Hi, can you check order FR-2026-0042 and draft a reply I can send to the customer?',
        ),
    ],
    logger: new Logger($logDir . '/ex4_' . date('Ymd_His') . '.json'),
);

$result = $agent->runTurn(
    $conversation,
    new ChatCompletionOptions(temperature: 0.2, topP: 0.9, toolChoice: 'auto'),
);

if (!$result->success || $result->message === null) {
    fwrite(STDERR, 'Agent failed: ' . ($result->error ?? 'unknown error') . PHP_EOL);
    exit(1);
}

echo "Assistant answer:\n";
echo trim($result->message->content) . PHP_EOL;
echo "\nTool rounds: {$result->toolRounds}\n";
