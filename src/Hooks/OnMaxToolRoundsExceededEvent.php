<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Hooks;

use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;

/** Payload dispatched when the agent exceeds the maximum allowed tool rounds. */
final readonly class OnMaxToolRoundsExceededEvent
{
    public function __construct(
        public Conversation          $conversation,
        public ChatCompletionOptions $options,
        public int                   $toolRounds,
        public int                   $maxToolRounds,
    ) {}
}
