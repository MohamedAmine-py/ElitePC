<?php

namespace App\Services\EliteAI\Contracts;

use Gemini\Data\Schema;

interface AgentTool
{
    public function name(): string;

    public function description(): string;

    public function schema(): Schema;

    /** Public read-only catalog execution. */
    public function execute(array $arguments): array;
}
