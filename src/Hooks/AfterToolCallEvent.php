<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Hooks;

use Tivins\LlmLib\Message;
use Tivins\LlmLib\ToolCall;

final readonly class AfterToolCallEvent
{
    public function __construct(
        public ToolCall $call,
        public Message  $toolMessage,
        public int      $toolRound,
    ) {}
}
