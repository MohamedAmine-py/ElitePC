<?php

namespace Tests\Fixtures;

use App\Models\User;
use App\Services\EliteAI\AgentContext;
use App\Services\EliteAI\Contracts\AuthenticatedAgentTool;
use App\Services\EliteAI\ToolArguments;
use Gemini\Data\Schema;
use RuntimeException;

// Test-only: deliberately absent from the production registry.
class ContextProbeTool extends AuthenticatedAgentTool
{
    public int $executions = 0;

    public ?User $seenUser = null;

    public bool $fail = false;

    public function __construct(private bool $mutating = true) {}

    public function name(): string
    {
        return 'context_probe';
    }

    public function description(): string
    {
        return 'Internal test probe.';
    }

    public function schema(): Schema
    {
        return ToolArguments::productIdSchema();
    }

    public function isMutating(): bool
    {
        return $this->mutating;
    }

    protected function run(array $arguments, AgentContext $context): array
    {
        ToolArguments::productId($arguments);
        $this->seenUser = $context->requireUser();
        $this->executions++;
        if ($this->fail) {
            throw new RuntimeException('Simulated unknown result after operation.');
        }

        return ['status' => 'ok', 'execution' => $this->executions];
    }
}
