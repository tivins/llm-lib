<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Hooks;

use Tivins\LlmLib\Conversation;
use Tivins\LlmLib\Message;

final readonly class AfterToolRoundEvent
{
    /**
     * @param Message[] $toolMessages
     */
    public function __construct(
        public Conversation $conversation,
        public array        $toolMessages,
        public int          $toolRound,
    ) {}
}
