<?php

/**
 * Example 8: explores library edge cases and remaining API surfaces.
 * Compared with Example 7, this covers dynamic tool registration, unknown-tool
 * recovery, max tool round limits, structured JSON output, and reasoning content
 * kept in logs but excluded from the next request payload.
 */

declare(strict_types=1);

use Tivins\LlmLib\Agent;
use Tivins\LlmLib\AgentHooks;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\Hooks\OnMaxToolRoundsExceededEvent;
use Tivins\LlmLib\LLM;
use Tivins\LlmLib\Logger;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;
use Tivins\LlmLib\Tool;
use Tivins\LlmLib\ToolCall;
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

echo "=== 1) ToolRegistry edge cases (no LLM call) ===\n";

$registry = new ToolRegistry();

$registry->registerTools(new Tool(
    new ToolSchema('lookup_order', 'Lookup order', ['type' => 'object', 'properties' => []]),
    handler: static fn (): string => json_encode(['handler' => 'v1']),
));

$registry->registerTools(new Tool(
    new ToolSchema('lookup_order', 'Lookup order', ['type' => 'object', 'properties' => []]),
    handler: static fn (): string => json_encode(['handler' => 'v2']),
));

$overwriteResult = $registry->execute(new ToolCall('call-1', 'lookup_order', '{}'));
echo 'Duplicate name overwrite: ' . $overwriteResult->content . PHP_EOL;

$unknownToolResult = $registry->execute(new ToolCall('call-2', 'missing_tool', '{}'));
echo 'Unknown tool error: ' . $unknownToolResult->content . PHP_EOL;

echo "\n=== 2) Structured JSON output via ChatCompletionOptions::responseFormat ===\n";

$jsonConversation = new Conversation([
    new Message(
        Role::System,
        'Return valid JSON only. No markdown, no explanation.',
    ),
    new Message(
        Role::User,
        'Give a JSON object with keys title, priority (low|medium|high), and tasks (array of 2 strings) for a deployment checklist.',
    ),
]);

$jsonResponse = $llm->chatCompletion(
    $jsonConversation,
    new ChatCompletionOptions(temperature: 0.1, responseFormat: 'json_object'),
);

$jsonText = trim($jsonResponse->firstChoice()?->message->content ?? '');
echo $jsonText . PHP_EOL;

$decoded = json_decode($jsonText, true);
echo 'JSON decode: ' . (is_array($decoded) ? 'ok' : 'failed') . PHP_EOL;

echo "\n=== 3) Agent: unknown tool recovery + max tool rounds ===\n";

$orders = [
    'FR-2026-0042' => ['status' => 'delayed', 'customer' => 'Camille Martin'],
    'FR-2026-0081' => ['status' => 'delivered', 'customer' => 'Nadia Benali'],
];

$dynamicTools = new ToolRegistry();
$dynamicTools->registerTools(new Tool(
    new ToolSchema(
        name: 'lookup_order',
        description: 'Look up one order by reference.',
        parameters: [
            'type' => 'object',
            'properties' => [
                'order_reference' => ['type' => 'string'],
            ],
            'required' => ['order_reference'],
            'additionalProperties' => false,
        ],
    ),
    handler: static function (string $arguments) use ($orders): string {
        $payload = json_decode($arguments, true);
        $reference = is_array($payload) ? ($payload['order_reference'] ?? '') : '';

        return json_encode(
            $orders[$reference] ?? ['error' => 'Order not found', 'order_reference' => $reference],
            JSON_THROW_ON_ERROR,
        );
    },
));

$maxRoundsHit = false;
$hooks = (new AgentHooks())
    ->onMaxToolRoundsExceeded(static function (OnMaxToolRoundsExceededEvent $event) use (&$maxRoundsHit): void {
        $maxRoundsHit = true;
        echo "[hook] max tool rounds exceeded: {$event->toolRounds}/{$event->maxToolRounds}\n";
    });

$agent = new Agent(
    llm: $llm,
    tools: $dynamicTools,
    maxToolRounds: 1,
    hooks: $hooks,
);

$agentConversation = new Conversation(
    messages: [
        new Message(
            Role::System,
            implode(' ', [
                'You are a support assistant.',
                'Use lookup_order for order facts.',
                'If get_weather is unavailable, continue with order facts only.',
                'Answer in clear, concise English.',
            ]),
        ),
        new Message(
            Role::User,
            implode(' ', [
                'Compare orders FR-2026-0042 and FR-2026-0081,',
                'and also tell me the weather in Paris using get_weather.',
            ]),
        ),
    ],
    logger: new Logger($logDir . '/ex8_' . date('Ymd_His') . '.json'),
);

$agentResult = $agent->runTurn(
    $agentConversation,
    new ChatCompletionOptions(temperature: 0.2, toolChoice: 'auto'),
);

if ($agentResult->success && $agentResult->message !== null) {
    echo "\nAgent answer:\n";
    echo trim($agentResult->message->content) . PHP_EOL;
    echo 'Tool rounds: ' . $agentResult->toolRounds . PHP_EOL;
} else {
    echo "\nAgent stopped: " . ($agentResult->error ?? 'unknown error') . PHP_EOL;
    echo 'Tool rounds: ' . $agentResult->toolRounds . PHP_EOL;
}

echo 'Max rounds hook fired: ' . ($maxRoundsHit ? 'yes' : 'no') . PHP_EOL;

$toolMessages = array_filter(
    $agentConversation->messages,
    static fn (Message $message): bool => $message->role === Role::Tool,
);

foreach ($toolMessages as $toolMessage) {
    if (str_contains($toolMessage->content, 'No handler for tool')) {
        echo 'Unknown tool recovered via tool message: ' . $toolMessage->content . PHP_EOL;
    }
}

echo "\n=== 4) reasoning_content: logged but not re-sent to the LLM ===\n";

$reasoningMessage = null;
foreach ($agentConversation->messages as $message) {
    if ($message->role === Role::Assistant && $message->reasoningContent !== null && $message->reasoningContent !== '') {
        $reasoningMessage = $message;
        break;
    }
}

if ($reasoningMessage !== null) {
    $preview = mb_substr(trim($reasoningMessage->reasoningContent), 0, 120);
    echo "Found reasoning_content in stored message: {$preview}...\n";
} else {
    echo "No reasoning_content returned by the model in this run.\n";
}

$payloadMessages = $agentConversation->toChatCompletionArray();
$reasoningInPayload = false;

foreach ($payloadMessages as $payloadMessage) {
    if (array_key_exists('reasoning_content', $payloadMessage)) {
        $reasoningInPayload = true;
        break;
    }
}

echo 'reasoning_content present in next LLM payload: ' . ($reasoningInPayload ? 'yes' : 'no') . PHP_EOL;
