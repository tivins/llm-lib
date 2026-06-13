<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** Parses and normalizes GPT-OSS / Harmony channel markers in assistant text. */
final class HarmonyContent
{
    private const CHANNEL = '<|channel|>';

    private const CHANNEL_ALT = '<|channel>';

    private const MESSAGE = '<|message|>';

    private const START = '<|start|>';

    public static function containsChannelMarkers(string $raw): bool
    {
        return str_contains($raw, self::CHANNEL) || str_contains($raw, self::CHANNEL_ALT);
    }

    /**
     * @return array{content: string, reasoning: ?string}
     */
    public static function parse(string $raw): array
    {
        if (!self::containsChannelMarkers($raw)) {
            return ['content' => $raw, 'reasoning' => null];
        }

        $jsonParsed = self::tryParseChannelJsonFormat($raw);
        if ($jsonParsed !== null) {
            return $jsonParsed;
        }

        if (!str_contains($raw, self::CHANNEL)) {
            return ['content' => self::stripTokens($raw), 'reasoning' => null];
        }

        $segments = preg_split('/(?=' . preg_quote(self::START, '/') . '|' . preg_quote(self::CHANNEL, '/') . ')/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if ($segments === false || $segments === []) {
            return ['content' => self::stripTokens($raw), 'reasoning' => null];
        }

        $reasoningParts = [];
        $finalParts = [];
        $fallbackParts = [];

        foreach ($segments as $segment) {
            $parsed = self::parseSegment($segment);
            if ($parsed === null) {
                $trimmed = trim($segment);
                if ($trimmed !== '') {
                    $fallbackParts[] = self::stripTokens($trimmed);
                }
                continue;
            }

            match ($parsed['channel']) {
                'analysis' => $reasoningParts[] = $parsed['content'],
                'final' => $finalParts[] = $parsed['content'],
                default => $fallbackParts[] = $parsed['content'],
            };
        }

        $reasoning = $reasoningParts !== [] ? implode("\n", $reasoningParts) : null;
        $content = $finalParts !== [] ? implode("\n", $finalParts) : implode("\n", array_filter($fallbackParts, static fn (string $part): bool => $part !== ''));

        if ($content === '' && $reasoning === null) {
            return ['content' => self::stripTokens($raw), 'reasoning' => null];
        }

        return ['content' => $content, 'reasoning' => $reasoning];
    }

    /**
     * Extract assistant text from llama.cpp "Failed to parse input at pos …" errors.
     *
     * @return array{content: string, reasoning: ?string}|null
     */
    public static function tryParseServerError(string $errorMessage): ?array
    {
        if (!str_starts_with($errorMessage, 'Failed to parse input at pos')) {
            return null;
        }

        $colonPos = strpos($errorMessage, ': ');
        if ($colonPos === false) {
            return null;
        }

        $raw = substr($errorMessage, $colonPos + 2);
        if ($raw === '') {
            return null;
        }

        $parsed = self::parse($raw);
        if ($parsed['content'] === '' && $parsed['reasoning'] === null) {
            return null;
        }

        return $parsed;
    }

    public static function stripTokens(string $text): string
    {
        $text = preg_replace('/<\|start\|>[^<]*/', '', $text) ?? $text;
        $text = preg_replace('/<\|channel\|?>[^<{]*/', '', $text) ?? $text;
        $text = str_replace(['<|message|>', '<|end|>', '<|return|>', '<|call|>'], '', $text);

        return trim($text);
    }

    /**
     * Some models emit `<|channel>TIMESTAMP\n{"thought":"…"}visible answer` instead of
     * the standard Harmony `<|channel|>analysis<|message|>…` sequence.
     *
     * @return array{content: string, reasoning: ?string}|null
     */
    private static function tryParseChannelJsonFormat(string $raw): ?array
    {
        if (!preg_match('/^<\|channel>\|?/s', $raw)) {
            return null;
        }

        if (preg_match('/^<\|channel\|>(analysis|final|commentary)</', $raw)) {
            return null;
        }

        $afterMarker = preg_replace('/^<\|channel>\|?/', '', $raw, 1);
        if ($afterMarker === null) {
            return null;
        }

        $bracePos = strpos($afterMarker, '{');
        if ($bracePos === false) {
            return null;
        }

        $json = self::extractJsonObject(substr($afterMarker, $bracePos));
        if ($json === null) {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $thought = $decoded['thought'] ?? $decoded['thinking'] ?? null;
        if (!is_string($thought)) {
            $thought = null;
        }

        $content = trim(substr($afterMarker, $bracePos + strlen($json)));
        if ($thought === null && $content === '') {
            return null;
        }

        return [
            'content' => $content,
            'reasoning' => $thought !== null && $thought !== '' ? $thought : null,
        ];
    }

    private static function extractJsonObject(string $text): ?string
    {
        if ($text === '' || $text[0] !== '{') {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($char === '\\' && $inString) {
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $inString = !$inString;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, 0, $i + 1);
                }
            }
        }

        return null;
    }

    /**
     * @return array{channel: string, content: string}|null
     */
    private static function parseSegment(string $segment): ?array
    {
        $segment = trim($segment);
        if ($segment === '') {
            return null;
        }

        if (str_starts_with($segment, self::START)) {
            $segment = substr($segment, strlen(self::START));
            $channelPos = strpos($segment, self::CHANNEL);
            if ($channelPos !== false) {
                $segment = substr($segment, $channelPos);
            }
        }

        if (!str_starts_with($segment, self::CHANNEL)) {
            return null;
        }

        $afterChannel = substr($segment, strlen(self::CHANNEL));
        $messagePos = strpos($afterChannel, self::MESSAGE);
        if ($messagePos === false) {
            return null;
        }

        $channel = trim(substr($afterChannel, 0, $messagePos));
        if ($channel === '') {
            return null;
        }

        $body = substr($afterChannel, $messagePos + strlen(self::MESSAGE));
        $content = self::extractMessageBody($body);
        if ($content === '') {
            return null;
        }

        return ['channel' => $channel, 'content' => $content];
    }

    private static function extractMessageBody(string $text): string
    {
        $endPos = strlen($text);
        foreach (['<|end|>', '<|return|>', '<|call|>', self::START] as $terminator) {
            $pos = strpos($text, $terminator);
            if ($pos !== false && $pos < $endPos) {
                $endPos = $pos;
            }
        }

        return trim(substr($text, 0, $endPos));
    }
}
