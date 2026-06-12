<?php

/**
 * Example 5: runs a more complete support workflow with several tools and
 * lifecycle hooks. Compared with Example 4, this adds multiple tool choices,
 * lightweight observability, and a final answer that combines order data with a
 * business policy.
 */

declare(strict_types=1);

use Tivins\LlmLib\Agent;
use Tivins\LlmLib\AgentHooks;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\Hooks\AfterLlmCallEvent;
use Tivins\LlmLib\Hooks\AfterToolCallEvent;
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
        'total_eur' => 89.90,
    ],
    'FR-2026-0081' => [
        'customer' => 'Nadia Benali',
        'status' => 'delivered',
        'carrier' => 'Chronopost',
        'last_event' => 'Delivered to pickup point',
        'estimated_delivery' => '2026-06-08',
        'total_eur' => 34.50,
    ],
];

$policies = [
    'delayed_delivery' => [
        'summary' => 'If delivery is more than 48 hours late, offer shipping-fee credit.',
        'customer_tone' => 'Apologize, explain the next update date, and avoid blaming the carrier.',
    ],
    'delivered_order' => [
        'summary' => 'For delivered orders, ask the customer to check the pickup point or mailbox first.',
        'customer_tone' => 'Be precise and invite the customer to reply if the package is still missing.',
    ],
];

$lookupOrder = new Tool(
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
);

$getPolicy = new Tool(
    new ToolSchema(
        name: 'get_support_policy',
        description: 'Fetch the internal support policy for a known case type.',
        parameters: [
            'type' => 'object',
            'properties' => [
                'case_type' => [
                    'type' => 'string',
                    'enum' => ['delayed_delivery', 'delivered_order'],
                ],
            ],
            'required' => ['case_type'],
            'additionalProperties' => false,
        ],
    ),
    handler: static function (string $arguments) use ($policies): string {
        $payload = json_decode($arguments, true);
        $caseType = is_array($payload) ? ($payload['case_type'] ?? '') : '';

        if (!is_string($caseType) || $caseType === '') {
            return json_encode(['error' => 'Missing case_type'], JSON_THROW_ON_ERROR);
        }

        return json_encode(
            $policies[$caseType] ?? ['error' => 'Unknown policy', 'case_type' => $caseType],
            JSON_THROW_ON_ERROR,
        );
    },
);

$hooks = (new AgentHooks())
    ->afterLlmCall(static function (AfterLlmCallEvent $event): void {
        printf(
            "[debug] LLM call finished in %.0f ms, tool round %d\n",
            $event->response->duration ?? 0.0,
            $event->toolRound,
        );
    })
    ->afterToolCall(static function (AfterToolCallEvent $event): void {
        printf("[debug] Tool called: %s\n", $event->call->name);
    });

$agent = new Agent(
    llm: $llm,
    tools: new ToolRegistry($lookupOrder, $getPolicy),
    maxToolRounds: 4,
    hooks: $hooks,
);

$conversation = new Conversation(
    messages: [
        new Message(
            Role::System,
            implode(' ', [
                'You are a senior customer-support assistant.',
                'Use tools for order facts and policy facts before making a recommendation.',
                'Return three sections: Diagnostic, Internal action, Customer message.',
                'Do not invent tracking events or policy details.',
            ]),
        ),
        new Message(
            Role::User,
            implode(' ', [
                'Customer Camille Martin is asking for a goodwill gesture on order FR-2026-0042.',
                'Verify the facts, apply the right policy, then draft the reply to send.',
            ]),
        ),
    ],
    logger: new Logger($logDir . '/ex5_' . date('Ymd_His') . '.json'),
);

$result = $agent->runTurn(
    $conversation,
    new ChatCompletionOptions(temperature: 0.2, topP: 0.9, toolChoice: 'auto'),
);

if (!$result->success || $result->message === null) {
    fwrite(STDERR, 'Agent failed: ' . ($result->error ?? 'unknown error') . PHP_EOL);
    exit(1);
}

echo "\nAssistant answer:\n";
echo trim($result->message->content) . PHP_EOL;
echo "\nTool rounds: {$result->toolRounds}\n";
