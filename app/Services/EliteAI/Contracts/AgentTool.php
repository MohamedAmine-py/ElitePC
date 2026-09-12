<?php

namespace App\Services\EliteAI\Contracts;

use Gemini\Data\Schema;

interface AgentTool
{
    public function name(): string;

    public function description(): string;

    public function schema(): Schema;

    /** Public read-only execution. Future private tools extend AuthenticatedAgentTool. */
    public function execute(array $arguments): array;
}
