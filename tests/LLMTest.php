<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\LLM;

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

        return ['tokens' => []];
    }
}
