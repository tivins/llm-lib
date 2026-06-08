<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

class AgentTurnResult
{
    public function __construct(
        public ?Message $message,
        public bool $success,
        public ?string $error = null,
        public int $toolRounds = 0,
    ) {}
}
