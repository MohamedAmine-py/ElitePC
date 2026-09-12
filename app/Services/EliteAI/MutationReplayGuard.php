<?php

namespace App\Services\EliteAI;

use Closure;
use RuntimeException;
use Throwable;

/** Lives with AgentContext, outside model-specific transcripts. No store data is mutated here. */
final class MutationReplayGuard
{
    private array $records = [];

    private array $callIds = [];

    private bool $frozen = false;

    public function __construct(private readonly string $executionId) {}

    public static function fingerprint(array $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (! is_array($item)) {
                return $item;
            }
            if (! array_is_list($item)) {
                ksort($item);
            }

            return array_map($normalize, $item);
        };

        return hash('sha256', json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    public function onFallback(): void
    {
        // After a write attempt, another model can replay known results but cannot invent new writes.
        $this->frozen = $this->frozen || $this->records !== [];
    }

    public function execute(string $tool, array $arguments, ?string $callId, Closure $operation): array
    {
        // Provider IDs can change or be absent after fallback. Canonical intent is stable within this request.
        $key = hash('sha256', $this->executionId.'|'.$tool.'|'.self::fingerprint($arguments));
        if ($callId !== null && $callId !== '') {
            if (isset($this->callIds[$callId]) && $this->callIds[$callId] !== $key) {
                throw new RuntimeException('Mutation call identity was reused with different arguments.');
            }
            $this->callIds[$callId] = $key;
        }
        if (isset($this->records[$key])) {
            if ($this->records[$key]['state'] !== 'complete') {
                throw new RuntimeException('Mutation outcome is uncertain; replay is blocked.');
            }

            return $this->records[$key]['result'];
        }
        if ($this->frozen) {
            throw new RuntimeException('New mutations after fallback require a new user request.');
        }

        // Reserve before entering business logic. Even exceptions must not cause another execution.
        $this->records[$key] = ['state' => 'pending'];
        try {
            $result = $operation();
            $this->records[$key] = ['state' => 'complete', 'result' => $result];

            return $result;
        } catch (Throwable $error) {
            $this->records[$key] = ['state' => 'uncertain'];
            throw $error;
        }
    }
}
