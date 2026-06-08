<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Hooks;

use Tivins\LlmLib\Message;
use Tivins\LlmLib\ToolCall;

/** Payload dispatched after a single tool call completes. */
final readonly class AfterToolCallEvent
{
    public function __construct(
        public ToolCall $call,
        public Message  $toolMessage,
        public int      $toolRound,
    ) {
    }
}
