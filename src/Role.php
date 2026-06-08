<?php
declare(strict_types=1);

namespace Tivins\LlmLib;

/** Identifies the speaker role of a chat message (system, user, assistant, or tool). */
enum Role: string
{
    case System = 'system';
    case Assistant = 'assistant';
    case User = 'user';
    case Tool = 'tool';
    case Unknown = 'unknown';
}