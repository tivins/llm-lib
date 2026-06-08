<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** Outcome of a single agent turn, including the assistant message and any error. */
class AgentTurnResult
{
    public function __construct(
        public ?Message $message,
        public bool $success,
        public ?string $error = null,
        public int $toolRounds = 0,
    ) {
    }
}
