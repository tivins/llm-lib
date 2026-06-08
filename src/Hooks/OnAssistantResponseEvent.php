<?php

declare(strict_types=1);

namespace Tivins\LlmLib\Hooks;

use Tivins\LlmLib\Message;

final class OnAssistantResponseEvent
{
    public string $visibleContent;

    public function __construct(
        public readonly Message $message,
        public readonly string $rawContent,
    ) {
        $this->visibleContent = $rawContent;
    }

    public function setVisibleContent(string $content): void
    {
        $this->visibleContent = $content;
    }
}
