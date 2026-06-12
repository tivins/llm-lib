<?php

/**
 * Example 6: runs a multi-turn agent session on the same conversation.
 * Compared with Example 5, this reuses one Agent across several user turns and
 * inspects assistant metadata (model, timing, token usage, finish_reason).
 */

declare(strict_types=1);

use Tivins\LlmLib\Agent;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\LLM;
use Tivins\LlmLib\Logger;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;
use Tivins\LlmLib\ToolRegistry;

require_once __DIR__ . '/../vendor/autoload.php';

$logDir = __DIR__ . '/../var/log';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$llm = new LLM(
    endpoint: 'http://127.0.0.1:8080',
    timeoutSeconds: 300,
);

$agent = new Agent(
    llm: $llm,
    tools: new ToolRegistry(),
);

$conversation = new Conversation(
    messages: [
        new Message(
            Role::System,
            'You are a concise assistant. Keep answers short and remember earlier turns.',
        ),
    ],
    logger: new Logger($logDir . '/ex6_' . date('Ymd_His') . '.json'),
);

$options = new ChatCompletionOptions(temperature: 0.2, topP: 0.9);

$userTurns = [
    'Draft an agenda for a 30-minute meeting about the website redesign.',
    'Now trim that agenda to at most 3 bullet points while keeping the same context.',
];

/**
 * @param array<string, mixed> $meta
 */
function printAssistantMeta(array $meta, int $turnNumber): void
{
    echo "\n--- Turn {$turnNumber} metadata ---\n";
    echo 'Model: ' . ($meta['model'] ?? 'n/a') . PHP_EOL;
    echo 'Duration: ' . ($meta['time_ms'] ?? 'n/a') . " ms\n";
    echo 'Finish reason: ' . ($meta['finish_reason'] ?? 'n/a') . PHP_EOL;
    echo 'Temperature: ' . ($meta['temperature'] ?? 'n/a') . PHP_EOL;

    if (isset($meta['usage']) && is_array($meta['usage'])) {
        printf(
            "Tokens: prompt=%d, completion=%d, total=%d\n",
            $meta['usage']['prompt_tokens'] ?? 0,
            $meta['usage']['completion_tokens'] ?? 0,
            $meta['usage']['total_tokens'] ?? 0,
        );
    }
}

foreach ($userTurns as $index => $userPrompt) {
    $turnNumber = $index + 1;

    $conversation->addMessage(Message::withCreatedAt(Role::User, $userPrompt));

    $result = $agent->runTurn($conversation, $options);

    if (!$result->success || $result->message === null) {
        fwrite(STDERR, "Turn {$turnNumber} failed: " . ($result->error ?? 'unknown error') . PHP_EOL);
        exit(1);
    }

    echo "\n=== Turn {$turnNumber} answer ===\n";
    echo trim($result->message->content) . PHP_EOL;

    printAssistantMeta($result->message->meta, $turnNumber);

    if ($result->message->reasoningContent !== null && $result->message->reasoningContent !== '') {
        $preview = substr(trim($result->message->reasoningContent), 0, 120);
        echo "Reasoning preview (logged only): {$preview}...\n";
    }
}

echo "\nConversation length: " . count($conversation->messages) . " messages\n";
