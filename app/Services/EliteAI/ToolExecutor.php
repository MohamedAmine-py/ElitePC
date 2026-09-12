<?php

namespace App\Services\EliteAI;

use App\Services\EliteAI\Contracts\AgentTool;
use App\Services\EliteAI\Contracts\AuthenticatedAgentTool;
use Illuminate\Validation\ValidationException;

final class ToolExecutor
{
    public function execute(AgentTool $tool, array $arguments, AgentContext $context, ?string $callId = null): array
    {
        $this->rejectIdentity($arguments);
        if (! $tool instanceof AuthenticatedAgentTool) {
            return $tool->execute($arguments);
        }
        $context->requireUser();
        $operation = fn () => $tool->executeWithContext($arguments, $context);

        return $tool->isMutating()
            ? $context->mutations->execute($tool->name(), $arguments, $callId, $operation)
            : $operation();
    }

    private function rejectIdentity(array $arguments): void
    {
        foreach ($arguments as $key => $value) {
            if ($key === 'user_id') {
                throw ValidationException::withMessages(['arguments' => 'Identity is server-controlled.']);
            }
            if (is_array($value)) {
                $this->rejectIdentity($value);
            }
        }
    }
}
