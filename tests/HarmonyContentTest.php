<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\HarmonyContent;

final class HarmonyContentTest extends TestCase
{
    public function testParseExtractsAnalysisAndFinalChannels(): void
    {
        $raw = '<|channel|>analysis<|message|>User asks for help.<|end|>'
            . '<|start|>assistant<|channel|>final<|message|>Here is the answer.<|return|>';

        self::assertSame([
            'content' => 'Here is the answer.',
            'reasoning' => 'User asks for help.',
        ], HarmonyContent::parse($raw));
    }

    public function testParseReturnsOriginalTextWhenNoChannelMarkers(): void
    {
        self::assertSame([
            'content' => 'Plain assistant reply.',
            'reasoning' => null,
        ], HarmonyContent::parse('Plain assistant reply.'));
    }

    public function testTryParseServerErrorExtractsEmbeddedAssistantText(): void
    {
        $error = 'Failed to parse input at pos 13: <|channel|>analysis<|message|>thinking<|end|>'
            . '<|start|>assistant<|channel|>final<|message|>visible answer<|return|>';

        self::assertSame([
            'content' => 'visible answer',
            'reasoning' => 'thinking',
        ], HarmonyContent::tryParseServerError($error));
    }

    public function testTryParseServerErrorReturnsNullForUnrelatedErrors(): void
    {
        self::assertNull(HarmonyContent::tryParseServerError('Connection refused'));
    }

    public function testStripTokensRemovesHarmonyMarkers(): void
    {
        $raw = '<|start|>assistant<|channel|>final<|message|>Hello<|return|>';

        self::assertSame('Hello', HarmonyContent::stripTokens($raw));
    }
}
