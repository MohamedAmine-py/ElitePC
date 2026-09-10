<?php

namespace App\Services\EliteAI\Contracts;

use Gemini\Data\Schema;

interface AgentTool
{
    public function name(): string;

    public function description(): string;

    public function schema(): Schema;

    /** Validate untrusted arguments before any data access. */
    public function execute(array $arguments): array;
}
