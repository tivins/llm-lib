<?php

/**
 * Example 1: runs the smallest useful single-turn chat completion against the
 * local LLM. This baseline only creates an LLM client, a conversation, request
 * options, and prints the assistant answer without logs, memory, agents, tools,
 * or multi-step orchestration.
 */

declare(strict_types=1);

use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\LLM;
use Tivins\LlmLib\Message;
use Tivins\LlmLib\Role;

require_once __DIR__ . '/../vendor/autoload.php';

$llm = new LLM(
    endpoint: 'http://127.0.0.1:8080',
    timeoutSeconds: 300,
);

$conversation = new Conversation([
    new Message(
        Role::System,
        'You are a concise assistant. Answer in English with practical details and no hidden reasoning.',
    ),
    new Message(
        Role::User,
        'I need to prepare for a client meeting in 10 minutes. Give me a short and realistic checklist.',
    ),
]);

$options = new ChatCompletionOptions(
    temperature: 0.2,
    topP: 0.9,
);

$response = $llm->chatCompletion($conversation, $options);
$message = $response->firstChoice()?->message;

if ($message === null) {
    fwrite(STDERR, 'No assistant message returned.' . PHP_EOL);
    exit(1);
}

echo "Assistant answer:\n";
echo trim($message->content) . PHP_EOL;
