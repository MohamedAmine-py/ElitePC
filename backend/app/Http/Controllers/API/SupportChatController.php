<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupportChatRequest;
use App\Services\EliteAI\AgentContext;
use App\Services\EliteAI\ChatExecutionStore;
use App\Services\EliteAI\EliteAgentService;
use App\Services\EliteAI\LegacyRagChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupportChatController extends Controller
{
    public function handleChat(SupportChatRequest $request, ChatExecutionStore $executions): JsonResponse
    {
        $input = ['message' => $request->validated('message'), 'history' => $request->validated('history') ?? []];
        $result = $executions->run($request->user(), $request->header('X-Chat-Request-ID'), $input, function (AgentContext $context) use ($input) {
            try {
                $reply = config('elite_ai.agent_enabled')
                    ? app(EliteAgentService::class)->reply($input['message'], $input['history'], $context)
                    : app(LegacyRagChatService::class)->reply($input['message'], $input['history']);

                return ['status' => 'success', 'reply' => $reply];
            } catch (Throwable $error) {
                // Never log prompts, credentials, SQL or raw provider errors (even in debug mode).
                Log::warning('Elite AI request could not be completed.', ['trace_id' => $context->executionId, 'type' => $error::class]);

                return [
                    'status' => 'error',
                    'reply' => 'I could not complete your request right now. Please try again or narrow your product question.',
                    'error' => 'Assistant temporarily unavailable.',
                ];
            }
        });

        return response()->json($result['body'])->header('X-Chat-Execution-ID', $result['execution_id']);
    }
}
