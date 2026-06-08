<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\ToolCall;

final class ToolCallTest extends TestCase
{
    public function testFromArrayAndToArrayAreSymmetric(): void
    {
        $payload = [
            'id' => 'call_abc',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"city":"Paris"}',
            ],
        ];

        $call = ToolCall::fromArray($payload);

        self::assertSame('call_abc', $call->id);
        self::assertSame('get_weather', $call->name);
        self::assertSame('{"city":"Paris"}', $call->arguments);
        self::assertSame([
            'id' => 'call_abc',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"city":"Paris"}',
            ],
        ], $call->toArray());
    }
}
