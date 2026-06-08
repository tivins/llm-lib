<?php
declare(strict_types=1);

namespace Tivins\LlmLib;

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