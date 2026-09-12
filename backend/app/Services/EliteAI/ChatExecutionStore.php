<?php

namespace App\Services\EliteAI;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Bounded transport replay cache, not conversation storage or durable business idempotency. */
final class ChatExecutionStore
{
    public const TTL_SECONDS = 3600;

    public function run(?User $user, ?string $clientId, array $input, Closure $reply): array
    {
        if ($clientId !== null) {
            Validator::make(['request_id' => $clientId], ['request_id' => 'required|uuid'])->validate();
            $clientId = strtolower($clientId);
        }
        $context = new AgentContext($user);
        // Guests have no trusted principal and no access to private tools: do not share cached replies.
        if ($user === null || $clientId === null) {
            return ['body' => $reply($context), 'execution_id' => $context->executionId];
        }

        $token = $user->currentAccessToken();
        $key = 'elite-ai:execution:'.hash('sha256', $user->getKey().'|'.$token?->getKey().'|'.$clientId);
        $fingerprint = MutationReplayGuard::fingerprint($input);
        $record = ['fingerprint' => $fingerprint, 'execution_id' => $context->executionId, 'state' => 'pending'];
        // Atomic reservation across PHP workers using the configured cache store.
        if (! Cache::add($key, $record, self::TTL_SECONDS)) {
            $previous = Cache::get($key);
            if (! $previous || $previous['fingerprint'] !== $fingerprint) {
                throw new ConflictHttpException('Chat request ID was already used for different input.');
            }
            if ($previous['state'] !== 'complete') {
                throw new ConflictHttpException('Chat request is pending or its outcome is uncertain; it was not executed again.');
            }

            return ['body' => $previous['body'], 'execution_id' => $previous['execution_id']];
        }

        // On a process failure, retain the pending reservation instead of attempting an unknown outcome again.
        $body = $reply($context);
        Cache::put($key, array_merge($record, ['state' => 'complete', 'body' => $body]), self::TTL_SECONDS);

        return ['body' => $body, 'execution_id' => $context->executionId];
    }
}
