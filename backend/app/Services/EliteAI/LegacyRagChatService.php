<?php

namespace App\Services\EliteAI;

use App\Services\GeminiRagService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/** Explicit rollback path, keeping the existing full-catalog RAG available. */
class LegacyRagChatService
{
    public function __construct(private GeminiRagService $rag, private GeminiTransport $transport) {}

    public function reply(string $message, array $history = []): string
    {
        $contents = ChatHistory::contents($message, $history);
        $instruction = $this->rag->buildSystemPrompt($this->rag->buildProductCatalogContext());
        foreach (GeminiTransport::MODELS as $model) {
            try {
                $reply = GeminiTransport::text($this->transport->generate($model, $instruction, $contents));
                if ($reply !== '') {
                    return $reply;
                }
            } catch (Throwable $error) {
                Log::warning('Elite AI RAG model unavailable.', ['model' => $model, 'type' => $error::class]);
            }
        }
        throw new RuntimeException('AI unavailable.');
    }
}
