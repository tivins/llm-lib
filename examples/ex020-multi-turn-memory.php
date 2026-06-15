<?php

/**
 * ex020 — two-turn conversation against the local LLM. Compared with
 * ex010, this adds conversation memory and persistent JSON logging, but it
 * still calls the LLM directly without agents or tools.
 */

declare(strict_types=1);

use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\LLM;
use Tivins\LlmLib\Logger;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;

require_once __DIR__ . '/../vendor/autoload.php';

$logDir = __DIR__ . '/../var/log';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$llm = new LLM(
    endpoint: 'http://127.0.0.1:8080',
    timeoutSeconds: 300,
);

$conversation = new Conversation(
    messages: [
        new Message(
            Role::System,
            'You are a concise assistant. Answer in English and keep the previous messages in mind.',
        ),
        new Message(
            Role::User,
            'Propose three simple ideas to reduce stress before a presentation.',
        ),
    ],
    logger: new Logger($logDir . '/ex2_' . date('Ymd_His') . '.json'),
);

$options = new ChatCompletionOptions(
    temperature: 0.2,
    topP: 0.9,
);

$firstResponse = $llm->chatCompletion($conversation, $options);
$firstMessage = $firstResponse->firstChoice()?->message;

if ($firstMessage === null) {
    fwrite(STDERR, 'No assistant message returned on first turn.' . PHP_EOL);
    exit(1);
}

$conversation->addMessage(new Message(Role::Assistant, $firstMessage->content));
$conversation->addMessage(new Message(
    Role::User,
    'Now turn these tips into a 5-minute routine, without repeating the whole list.',
));

$secondResponse = $llm->chatCompletion($conversation, $options);
$secondMessage = $secondResponse->firstChoice()?->message;

if ($secondMessage === null) {
    fwrite(STDERR, 'No assistant message returned on second turn.' . PHP_EOL);
    exit(1);
}

echo "First answer:\n";
echo trim($firstMessage->content) . PHP_EOL;

echo "\nSecond answer using conversation memory:\n";
echo trim($secondMessage->content) . PHP_EOL;
