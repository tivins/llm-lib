<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Tests;

use PHPUnit\Framework\TestCase;
use Tivins\LlmLib\Role;
use Tivins\LlmLib\ToolCall;
use Tivins\LlmLib\ToolCallRejection;

final class ToolCallRejectionTest extends TestCase
{
    public function testUserRejectedBuildsStandardPayload(): void
    {
        $call = new ToolCall('call-1', 'write_file', '{"path":"notes.txt"}');
        $message = ToolCallRejection::userRejected($call);

        self::assertSame(Role::Tool, $message->role);
        self::assertSame('call-1', $message->toolCallId);
        self::assertSame(
            json_encode([
                'error' => ToolCallRejection::DEFAULT_USER_MESSAGE,
                'code' => ToolCallRejection::CODE_USER_REJECTED,
            ], JSON_THROW_ON_ERROR),
            $message->content,
        );
    }

    public function testUserRejectedAcceptsCustomReason(): void
    {
        $call = new ToolCall('call-2', 'run_command', '{}');
        $message = ToolCallRejection::userRejected($call, 'Path outside allowed workspace');

        self::assertSame(
            json_encode([
                'error' => 'Path outside allowed workspace',
                'code' => ToolCallRejection::CODE_USER_REJECTED,
            ], JSON_THROW_ON_ERROR),
            $message->content,
        );
    }
}
