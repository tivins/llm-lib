<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Hooks;

use Tivins\LlmLib\Message;
use Tivins\LlmLib\ToolCall;

/** Payload dispatched before a single tool call; listeners may supply a replacement result. */
final class BeforeToolCallEvent
{
    /** If set, the real tool handler is skipped. */
    public ?Message $replacement = null;

    public function __construct(
        public readonly ToolCall $call,
        public readonly int $toolRound,
    ) {}
}
