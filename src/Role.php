<?php
declare(strict_types=1);

namespace Tivins\LlmLib;

enum Role: string
{
    case System = 'system';
    case Assistant = 'assistant';
    case User = 'user';
    case Tool = 'tool';
    case Unknown = 'unknown';
}