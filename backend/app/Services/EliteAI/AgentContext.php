<?php

namespace App\Services\EliteAI;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;

final class AgentContext
{
    public readonly string $executionId;

    public readonly MutationReplayGuard $mutations;

    public function __construct(public readonly ?User $user = null)
    {
        $this->executionId = (string) Str::uuid();
        $this->mutations = new MutationReplayGuard($this->executionId);
    }

    public function requireUser(): User
    {
        return $this->user ?? throw new AuthenticationException;
    }
}
