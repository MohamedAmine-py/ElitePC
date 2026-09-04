<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupportChatRequest;
use App\Services\GeminiRagService;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Data\Content;
use Gemini\Enums\Role;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * SupportChatController — AI-powered customer support for Elite PC.
 *
 * Architecture:
 * 1. Incoming user message is validated & sanitized by SupportChatRequest.
 * 2. GeminiRagService fetches the live product catalog from the database
 *    and builds a context-enriched system prompt (RAG pattern).
 * 3. The Gemini Flash-Lite model generates a response grounded in real data.
 * 4. Rate limiting is enforced at the route level (throttle:ai-chat).
 */
class SupportChatController extends Controller
{
    /**
     * Inject the RAG service via constructor dependency injection.
     */
    public function __construct(
        private readonly GeminiRagService $ragService
    ) {}

    /**
     * Handle support chat agent requests and integrate with Google Gemini API.
     *
     * The RAG pipeline:
     *   1. RETRIEVE  → Fetch live product catalog from MySQL/SQLite via Eloquent.
     *   2. AUGMENT   → Inject the catalog into the system prompt alongside grounding rules.
     *   3. GENERATE  → Send the enriched prompt + user message to Gemini Flash-Lite.
     *
     * @param  SupportChatRequest  $request  Validated & sanitized request.
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleChat(SupportChatRequest $request)
    {
        $validated = $request->validated();
        $message = $validated['message'];
        $historyInput = array_slice(
            $validated['history'] ?? [],
            -SupportChatRequest::HISTORY_LIMIT,
        );

        // ── Step 1: Reconstruct conversation history ────────────────
        // Map frontend roles to Gemini-native Content structures
        $history = [];
        foreach ($historyInput as $msg) {
            $role = ($msg['role'] === 'model' || $msg['role'] === 'assistant')
                ? Role::MODEL
                : Role::USER;
            $history[] = Content::parse(part: $msg['content'], role: $role);
        }

        // ── Step 2: Build RAG-enriched system prompt ────────────────
        // Fetch live product catalog from the database
        $catalogContext = $this->ragService->buildProductCatalogContext();

        // Construct the full system instruction with grounding rules + catalog data
        $systemInstruction = $this->ragService->buildSystemPrompt($catalogContext);

        // ── Security: Verify API key via config() (not env()) ────
        if (empty(config('gemini.api_key'))) {
            Log::error('Gemini API key is not configured in the server environment.');
            return response()->json([
                'status' => 'error',
                'reply'  => "Hello! I am currently experiencing a minor technical delay. "
                          . "Please give me a brief moment, or let me know how I can help "
                          . "once my systems are fully back online! (Elite PC Assistant offline)",
                'error'  => 'Gemini API key is not configured.'
            ], 200);
        }

        // ── Step 3: Generate AI response with robust fallback chain ──
        // Multi-model fallback chain to ensure high availability during API rate-limiting or high-demand spikes
        $models = [
            'gemini-2.5-flash-lite',     // Preferred Flash-Lite
            'gemini-flash-lite-latest',  // Stable Flash-Lite Alias
            'gemini-2.5-flash',          // Stable High-Capacity Flash
            'gemini-flash-latest'        // Stable High-Capacity Flash Alias
        ];

        $reply = null;
        $lastException = null;

        foreach ($models as $model) {
            try {
                $chat = Gemini::generativeModel(model: $model)
                    ->withSystemInstruction(Content::parse($systemInstruction))
                    ->startChat(history: $history);

                $response = $chat->sendMessage($message);
                $reply    = $response->text();

                if (!empty($reply)) {
                    Log::info("SupportChatController: AI chat succeeded using model '{$model}'.");
                    break;
                }
            } catch (Exception $e) {
                $lastException = $e;
                Log::warning("SupportChatController: Model '{$model}' failed: " . $e->getMessage() . ". Attempting next fallback model...");
            }
        }

        try {
            if (empty($reply)) {
                throw $lastException ?? new Exception('All models in the Gemini fallback chain failed to generate content.');
            }

            return response()->json([
                'status' => 'success',
                'reply'  => $reply,
            ]);

        } catch (Exception $e) {
            Log::error('Gemini Support Chat Error: ' . $e->getMessage(), [
                'user_message' => $message,
                'trace'        => $e->getTraceAsString(),
            ]);

            // Return a friendly, on-brand fallback message
            // Use 200 so the frontend can display the fallback instead of crashing
            return response()->json([
                'status' => 'error',
                'reply'  => "Hello! I'm experiencing a brief technical delay. "
                          . "Please try again in a moment, or let me know how I can help "
                          . "once my systems are fully back online! (Elite PC Assistant offline)",
                'error'  => config('app.debug') ? $e->getMessage() : 'An internal error occurred.',
            ], 200);
        }
    }
}
