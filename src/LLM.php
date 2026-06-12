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

        $data = $this->request('POST', '/v1/chat/completions', $body);

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
            $choices[] = new Choice(
                $choice['index'],
                new Message(
                    Role::tryFrom($choice['message']['role']) ?? Role::Unknown,
                    $choice['message']['content'] ?? '',
                    $choice['message']['reasoning_content'] ?? null,
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
}
