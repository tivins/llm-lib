<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\EmbeddingOptions;
use Tivins\LlmLib\LLM;
use Tivins\LlmLib\RerankOptions;
use Tivins\LlmLib\Role;

final class LLMTest extends TestCase
{
    public function testTokenizeReturnsIntegerTokenIds(): void
    {
        $llm = new class ('http://stub.test') extends LLM {
            protected function request(string $method, string $path, ?string $body = null): array
            {
                return ['tokens' => [42, 7, 108]];
            }
        };

        self::assertSame([42, 7, 108], $llm->tokenize('hello world'));
    }

    public function testTokenizeExtractsIdsFromWithPiecesResponse(): void
    {
        $llm = new class ('http://stub.test') extends LLM {
            protected function request(string $method, string $path, ?string $body = null): array
            {
                return [
                    'tokens' => [
                        ['id' => 1, 'piece' => 'Hello'],
                        ['id' => 2, 'piece' => ' world'],
                    ],
                ];
            }
        };

        self::assertSame([1, 2], $llm->tokenize('Hello world'));
    }

    public function testTokenizeSendsContentToTokenizeEndpoint(): void
    {
        $llm = new CapturingLLM('http://stub.test');

        $llm->tokenize('ping');

        self::assertSame('POST', $llm->method);
        self::assertSame('/tokenize', $llm->path);
        self::assertSame('{"content":"ping"}', $llm->body);
    }

    public function testTokenizeThrowsWhenTokensMissing(): void
    {
        $llm = new class ('http://stub.test') extends LLM {
            protected function request(string $method, string $path, ?string $body = null): array
            {
                return [];
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('LLM tokenize response missing tokens');

        $llm->tokenize('x');
    }

    public function testChatCompletionNormalizesHarmonyContentFromResponse(): void
    {
        $llm = new class ('http://stub.test') extends LLM {
            protected function request(string $method, string $path, ?string $body = null): array
            {
                return [
                    'model' => 'gpt-oss-test',
                    'choices' => [[
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => '<|channel|>analysis<|message|>thinking<|end|>'
                                . '<|start|>assistant<|channel|>final<|message|>answer<|return|>',
                        ],
                        'finish_reason' => 'stop',
                    ]],
                    'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 2, 'total_tokens' => 3],
                ];
            }
        };

        $response = $llm->chatCompletion(new Conversation(), new ChatCompletionOptions());
        $assistant = $response->assistantMessage();

        self::assertNotNull($assistant);
        self::assertSame('answer', $assistant->content);
        self::assertSame('thinking', $assistant->reasoningContent);
    }

    public function testChatCompletionRecoversFromHarmonyParseServerError(): void
    {
        $llm = new class ('http://stub.test', defaultModel: 'gpt-oss-test') extends LLM {
            protected function request(string $method, string $path, ?string $body = null): array
            {
                throw new Exception(
                    'LLM request failed: Failed to parse input at pos 13: <|channel|>analysis<|message|>thought<|end|>'
                    . '<|start|>assistant<|channel|>final<|message|>recovered answer<|return|>',
                );
            }
        };

        $response = $llm->chatCompletion(new Conversation(), new ChatCompletionOptions());
        $assistant = $response->assistantMessage();

        self::assertNotNull($assistant);
        self::assertSame(Role::Assistant, $assistant->role);
        self::assertSame('recovered answer', $assistant->content);
        self::assertSame('thought', $assistant->reasoningContent);
    }

    public function testChatCompletionNormalizesTimestampedChannelJsonFormat(): void
    {
        $llm = new class ('http://stub.test') extends LLM {
            protected function request(string $method, string $path, ?string $body = null): array
            {
                return [
                    'model' => 'thinker-test',
                    'choices' => [[
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => '<|channel>2024-10-11T16:40:54.384Z' . "\n"
                                . '{"thought":"internal reasoning"}Le début de la réponse',
                        ],
                        'finish_reason' => 'stop',
                    ]],
                    'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 2, 'total_tokens' => 3],
                ];
            }
        };

        $response = $llm->chatCompletion(new Conversation(), new ChatCompletionOptions());
        $assistant = $response->assistantMessage();

        self::assertNotNull($assistant);
        self::assertSame('Le début de la réponse', $assistant->content);
        self::assertSame('internal reasoning', $assistant->reasoningContent);
    }

    public function testEmbeddingsReturnsFloatVectors(): void
    {
        $llm = new class ('http://stub.test') extends LLM {
            protected function request(string $method, string $path, ?string $body = null): array
            {
                return [
                    'model' => 'embed-test',
                    'data' => [
                        ['index' => 0, 'object' => 'embedding', 'embedding' => [0.1, -0.2, 0.3]],
                        ['index' => 1, 'object' => 'embedding', 'embedding' => [1.0, 2.0]],
                    ],
                    'usage' => ['prompt_tokens' => 5, 'total_tokens' => 5],
                ];
            }
        };

        $response = $llm->embeddings(['hello', 'world']);

        self::assertSame('embed-test', $response->model);
        self::assertSame(5, $response->usage->promptTokens);
        self::assertSame(0, $response->usage->completionTokens);
        self::assertSame(5, $response->usage->totalTokens);
        self::assertCount(2, $response->embeddings);
        self::assertSame([0.1, -0.2, 0.3], $response->embeddings[0]->vector);
        self::assertSame([1.0, 2.0], $response->embeddings[1]->vector);
        self::assertSame([0.1, -0.2, 0.3], $response->first()?->vector);
        self::assertSame([[0.1, -0.2, 0.3], [1.0, 2.0]], $response->vectors());
    }

    public function testEmbeddingsSendsInputAndOptionsToEndpoint(): void
    {
        $llm = new CapturingLLM('http://stub.test', defaultModel: 'default-embed');

        $llm->embeddings(
            'ping',
            new EmbeddingOptions(model: 'bge-m3', encodingFormat: 'float', dimensions: 256),
        );

        self::assertSame('POST', $llm->method);
        self::assertSame('/v1/embeddings', $llm->path);
        self::assertSame(
            '{"input":"ping","model":"bge-m3","encoding_format":"float","dimensions":256}',
            $llm->body,
        );
    }

    public function testEmbeddingsDecodesBase64Vectors(): void
    {
        $encoded = base64_encode(pack('g', 0.5) . pack('g', -1.25));

        $llm = new class ('http://stub.test') extends LLM {
            protected function request(string $method, string $path, ?string $body = null): array
            {
                return [
                    'model' => 'embed-test',
                    'data' => [
                        ['index' => 0, 'object' => 'embedding', 'embedding' => base64_encode(pack('g', 0.5) . pack('g', -1.25))],
                    ],
                    'usage' => ['prompt_tokens' => 1, 'total_tokens' => 1],
                ];
            }
        };

        $response = $llm->embeddings(
            'hello',
            new EmbeddingOptions(encodingFormat: 'base64'),
        );

        self::assertCount(1, $response->embeddings);
        self::assertEqualsWithDelta(0.5, $response->embeddings[0]->vector[0], 0.0001);
        self::assertEqualsWithDelta(-1.25, $response->embeddings[0]->vector[1], 0.0001);
    }

    public function testEmbeddingsThrowsWhenDataMissing(): void
    {
        $llm = new class ('http://stub.test') extends LLM {
            protected function request(string $method, string $path, ?string $body = null): array
            {
                return ['model' => 'embed-test'];
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('LLM embeddings response missing data');

        $llm->embeddings('x');
    }

    public function testRerankReturnsScoredResults(): void
    {
        $llm = new class ('http://stub.test') extends LLM {
            protected function request(string $method, string $path, ?string $body = null): array
            {
                return [
                    'model' => 'rerank-test',
                    'results' => [
                        ['index' => 1, 'relevance_score' => 0.2],
                        ['index' => 0, 'relevance_score' => 0.9],
                    ],
                    'usage' => ['prompt_tokens' => 10, 'total_tokens' => 10],
                ];
            }
        };

        $documents = ['panda bear', 'stock market'];
        $response = $llm->rerank('What is a panda?', $documents);

        self::assertSame('rerank-test', $response->model);
        self::assertSame(10, $response->usage->totalTokens);
        self::assertCount(2, $response->results);
        self::assertSame(1, $response->results[0]->index);
        self::assertEqualsWithDelta(0.2, $response->results[0]->relevanceScore, 0.0001);

        $sorted = $response->sortedResults();
        self::assertSame(0, $sorted[0]->index);
        self::assertEqualsWithDelta(0.9, $sorted[0]->relevanceScore, 0.0001);

        $ranked = $response->rankedDocuments($documents);
        self::assertSame([
            ['index' => 0, 'document' => 'panda bear', 'relevanceScore' => 0.9],
            ['index' => 1, 'document' => 'stock market', 'relevanceScore' => 0.2],
        ], $ranked);
    }

    public function testRerankSendsQueryDocumentsAndOptionsToEndpoint(): void
    {
        $llm = new CapturingLLM('http://stub.test', defaultModel: 'default-rerank');

        $llm->rerank(
            'What is a panda?',
            ['doc a', 'doc b'],
            new RerankOptions(model: 'qwen3-reranker', topN: 2),
        );

        self::assertSame('POST', $llm->method);
        self::assertSame('/v1/rerank', $llm->path);
        self::assertSame(
            '{"query":"What is a panda?","documents":["doc a","doc b"],"model":"qwen3-reranker","top_n":2}',
            $llm->body,
        );
    }

    public function testRerankThrowsWhenResultsMissing(): void
    {
        $llm = new class ('http://stub.test') extends LLM {
            protected function request(string $method, string $path, ?string $body = null): array
            {
                return ['model' => 'rerank-test'];
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('LLM rerank response missing results');

        $llm->rerank('query', ['doc']);
    }
}

final class CapturingLLM extends LLM
{
    public string $method = '';

    public string $path = '';

    public ?string $body = null;

    protected function request(string $method, string $path, ?string $body = null): array
    {
        $this->method = $method;
        $this->path = $path;
        $this->body = $body;

        if ($path === '/v1/embeddings') {
            return [
                'model' => 'capture-test',
                'data' => [
                    ['index' => 0, 'object' => 'embedding', 'embedding' => [0.0]],
                ],
                'usage' => ['prompt_tokens' => 1, 'total_tokens' => 1],
            ];
        }

        if ($path === '/v1/rerank') {
            return [
                'model' => 'capture-test',
                'results' => [
                    ['index' => 0, 'relevance_score' => 1.0],
                ],
                'usage' => ['prompt_tokens' => 1, 'total_tokens' => 1],
            ];
        }

        return ['tokens' => []];
    }
}
