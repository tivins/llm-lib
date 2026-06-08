<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Hooks;

use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;

/** Payload dispatched before an agent turn starts. */
final readonly class BeforeTurnEvent
{
    public function __construct(
        public Conversation          $conversation,
        public ChatCompletionOptions $options,
    ) {}
}
