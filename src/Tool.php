<?php
declare(strict_types=1);

namespace Tivins\LlmLib;

/** Pairs a tool schema with its callable handler for agent execution. */
class Tool
{
    public $handler;

    public function __construct(
        public ToolSchema $schema,
        callable          $handler
    )
    {
        $this->handler = $handler;
    }
}