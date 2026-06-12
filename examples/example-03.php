<?php

/**
 * Example 3: runs one turn through an Agent with no tools. Compared with
 * Example 2, this adds Agent orchestration and structured success/error
 * handling, while keeping the task text-only and intentionally simple.
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
    maxToolRounds: 0,
);

$conversation = new Conversation(
    messages: [
        new Message(
            Role::System,
            'You are a pragmatic product assistant. Answer in English with a short, actionable structure.',
        ),
        new Message(
            Role::User,
            'We are releasing a small note-taking app. Please suggest a realistic manual testing plan.',
        ),
    ],
    logger: new Logger($logDir . '/ex3_' . date('Ymd_His') . '.json'),
);

$result = $agent->runTurn(
    $conversation,
    new ChatCompletionOptions(temperature: 0.2, topP: 0.9),
);

if (!$result->success || $result->message === null) {
    fwrite(STDERR, 'Agent failed: ' . ($result->error ?? 'unknown error') . PHP_EOL);
    exit(1);
}

echo "Agent answer:\n";
echo trim($result->message->content) . PHP_EOL;
