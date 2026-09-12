<?php

namespace App\Services\EliteAI\Contracts;

use App\Services\EliteAI\AgentContext;
use Illuminate\Auth\AuthenticationException;

/** Opt-in extension for future private reads/writes; existing public tools stay unchanged. */
abstract class AuthenticatedAgentTool implements AgentTool
{
    abstract public function isMutating(): bool;

    final public function execute(array $arguments): array
    {
        // A private tool must never be usable through the legacy public-tool path.
        throw new AuthenticationException;
    }

    final public function executeWithContext(array $arguments, AgentContext $context): array
    {
        $context->requireUser();

        return $this->run($arguments, $context);
    }

    abstract protected function run(array $arguments, AgentContext $context): array;
}
