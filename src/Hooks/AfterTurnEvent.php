<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Hooks;

use Tivins\LlmLib\AgentTurnResult;
use Tivins\LlmLib\ChatCompletionOptions;
use Tivins\LlmLib\Conversation;

final readonly class AfterTurnEvent
{
    public function __construct(
        public Conversation          $conversation,
        public ChatCompletionOptions $options,
        public AgentTurnResult       $result,
    ) {}
}
