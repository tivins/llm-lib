<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** Builds standard tool messages for skipped tool execution (e.g. user rejection). */
final class ToolCallRejection
{
    public const string CODE_USER_REJECTED = 'user_rejected';

    public const string DEFAULT_USER_MESSAGE = 'Action rejected by the user';

    public static function userRejected(ToolCall $call, ?string $reason = null): Message
    {
        return new Message(
            Role::Tool,
            json_encode([
                'error' => $reason ?? self::DEFAULT_USER_MESSAGE,
                'code' => self::CODE_USER_REJECTED,
            ], JSON_THROW_ON_ERROR),
            toolCallId: $call->id,
        );
    }
}
