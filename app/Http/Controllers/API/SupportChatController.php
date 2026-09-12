<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupportChatRequest;
use App\Services\EliteAI\EliteAgentService;
use App\Services\EliteAI\LegacyRagChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupportChatController extends Controller
{
    public function handleChat(SupportChatRequest $request): JsonResponse
    {
        try {
            $message = $request->validated('message');
            $history = $request->validated('history') ?? [];
            $reply = config('elite_ai.agent_enabled')
                ? app(EliteAgentService::class)->reply($message, $history)
                : app(LegacyRagChatService::class)->reply($message, $history);

            return response()->json(['status' => 'success', 'reply' => $reply]);
        } catch (Throwable $error) {
            // Never log prompts, credentials, SQL or raw provider errors (even in debug mode).
            Log::warning('Elite AI request could not be completed.', ['type' => $error::class]);

            return response()->json([
                'status' => 'error',
                'reply' => 'I could not complete your request right now. Please try again or narrow your product question.',
                'error' => 'Assistant temporarily unavailable.',
            ]);
        }
    }
}
