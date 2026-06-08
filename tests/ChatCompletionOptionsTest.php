<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Tool;
use Tivins\LlmLib\ToolRegistry;
use Tivins\LlmLib\ToolSchema;

final class ChatCompletionOptionsTest extends TestCase
{
    public function testToRequestArrayUsesDefaultModelWhenOptionIsNull(): void
    {
        $options = new ChatCompletionOptions(model: null, temperature: 0.2);

        self::assertSame([
            'temperature' => 0.2,
            'top_p' => 1.0,
            'n' => 1,
            'model' => 'default-model',
        ], $options->toRequestArray('default-model'));
    }

    public function testToolsAreOmittedWhenRegistryIsEmpty(): void
    {
        $options = new ChatCompletionOptions(tools: new ToolRegistry());

        $body = $options->toRequestArray(null);

        self::assertArrayNotHasKey('tools', $body);
        self::assertArrayNotHasKey('tool_choice', $body);
    }

    public function testToolsAndToolChoiceAreIncludedWhenRegistryHasTools(): void
    {
        $tools = new ToolRegistry(
            new Tool(
                new ToolSchema('ping', 'Ping', ['type' => 'object']),
                fn (): string => 'pong',
            ),
        );
        $options = new ChatCompletionOptions(tools: $tools, toolChoice: 'auto');

        $body = $options->toRequestArray('m1');

        self::assertArrayHasKey('tools', $body);
        self::assertSame('auto', $body['tool_choice']);
        self::assertSame('m1', $body['model']);
    }

    public function testResponseFormatIsWrapped(): void
    {
        $options = new ChatCompletionOptions(responseFormat: 'json_object');

        self::assertSame(
            ['type' => 'json_object'],
            $options->toRequestArray(null)['response_format'],
        );
    }
}
