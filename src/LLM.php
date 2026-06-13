<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

use Exception;

/** HTTP client for OpenAI-compatible chat completion endpoints. */
class LLM
{
    public function __construct(
        public string $endpoint,
        public ?string $apiKey = null,
        public ?string $defaultModel = null,
        public int $timeoutSeconds = 120,
    ) {
    }

    /**
     * @return list<int>
     *
     * @throws Exception
     */
    public function tokenize(string $text): array
    {
        $data = $this->request('POST', '/tokenize', json_encode(['content' => $text]));

        if (!isset($data['tokens']) || !is_array($data['tokens'])) {
            throw new Exception('LLM tokenize response missing tokens');
        }

        return array_map(
            static fn (mixed $token): int => is_array($token) ? (int) ($token['id'] ?? 0) : (int) $token,
            $data['tokens'],
        );
    }

    /**
     * @throws Exception
     */
    public function chatCompletion(Conversation $conversation, ChatCompletionOptions $options): ChatCompletionResponse
    {
        $start = hrtime(true);
        $body = json_encode(array_merge(
            ['messages' => $conversation->toChatCompletionArray()],
            $options->toRequestArray($this->defaultModel),
        ));

        try {
            $data = $this->request('POST', '/v1/chat/completions', $body);
        } catch (Exception $e) {
            $data = $this->tryRecoverHarmonyParseError($e);
            if ($data === null) {
                throw $e;
            }
        }

        if (!isset($data['choices']) || !is_array($data['choices'])) {
            throw new Exception('LLM response missing choices');
        }

        $usage = new Usage(
            $data['usage']['prompt_tokens'] ?? 0,
            $data['usage']['completion_tokens'] ?? 0,
            $data['usage']['total_tokens'] ?? 0,
        );

        $choices = [];
        foreach ($data['choices'] as $choice) {
            $toolCalls = null;
            if (isset($choice['message']['tool_calls'])) {
                $toolCalls = array_map(
                    ToolCall::fromArray(...),
                    $choice['message']['tool_calls'],
                );
            }
            [$content, $reasoningContent] = self::normalizeAssistantContent(
                $choice['message']['content'] ?? '',
                $choice['message']['reasoning_content'] ?? null,
            );

            $choices[] = new Choice(
                $choice['index'],
                new Message(
                    Role::tryFrom($choice['message']['role']) ?? Role::Unknown,
                    $content,
                    $reasoningContent,
                    toolCalls: $toolCalls,
                    toolCallId: $choice['message']['tool_call_id'] ?? null,
                ),
                $choice['finish_reason'],
            );
        }
        $elapsedMs = (hrtime(true) - $start) / 1e6;

        return new ChatCompletionResponse(
            $data['model'],
            $usage,
            $choices,
            $data,
            $elapsedMs,
        );
    }

    // Get models list : GET /v1/models
    // public function listModels(): array

    // Load model : POST /models/load
    // public function loadModel(string $modelName): void

    // Unload model : POST /models/unload
    // public function unloadModel(string $modelName): void

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    protected function request(string $method, string $path, ?string $body = null): array
    {
        $url = $this->endpoint . $path;
        $headers = ['Content-Type: application/json'];
        if ($this->apiKey !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, min(10, $this->timeoutSeconds));
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeoutSeconds);

        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            }
        } elseif ($method === 'GET') {
            curl_setopt($curl, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new Exception($error);
        }
        curl_close($curl);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new Exception('Invalid JSON response from LLM: ' . substr((string) $response, 0, 500));
        }

        if ($httpCode >= 400 || isset($data['error'])) {
            $message = $data['error']['message'] ?? $data['error'] ?? "HTTP $httpCode";
            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE);
            }

            throw new Exception('LLM request failed: ' . $message);
        }

        return $data;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private static function normalizeAssistantContent(string $content, ?string $reasoningContent): array
    {
        if (!str_contains($content, '<|channel|>')) {
            return [$content, $reasoningContent];
        }

        $parsed = HarmonyContent::parse($content);
        $reasoning = $parsed['reasoning'];
        if ($reasoningContent !== null && $reasoningContent !== '') {
            $reasoning = $reasoning !== null && $reasoning !== ''
                ? $reasoningContent . "\n" . $reasoning
                : $reasoningContent;
        }

        return [$parsed['content'], $reasoning];
    }

    /**
     * llama.cpp may return HTTP 500 after a successful generation when its Harmony
     * autoparser fails on the raw output. Recover when the error embeds parseable text.
     *
     * @return array<string, mixed>|null
     */
    private function tryRecoverHarmonyParseError(Exception $e): ?array
    {
        $prefix = 'LLM request failed: ';
        $message = $e->getMessage();
        if (!str_starts_with($message, $prefix)) {
            return null;
        }

        $parsed = HarmonyContent::tryParseServerError(substr($message, strlen($prefix)));
        if ($parsed === null || $parsed['content'] === '') {
            return null;
        }

        return [
            'model' => $this->defaultModel ?? 'unknown',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => $parsed['content'],
                    'reasoning_content' => $parsed['reasoning'],
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
            ],
        ];
    }
}
