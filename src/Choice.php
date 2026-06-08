<?php

declare(strict_types=1);

namespace Tivins\LlmLib;

/** One completion choice returned by the LLM API. */
class Choice
{
    public function __construct(
        public int     $index,
        public Message $message,
        public string  $finishReason,
    ) {
    }
}
