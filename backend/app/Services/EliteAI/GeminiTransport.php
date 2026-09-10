<?php

namespace App\Services\EliteAI;

use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\FinishReason;
use Gemini\Laravel\Facades\Gemini;
use RuntimeException;

/** Uses the existing SDK provider: gemini.api_key, base_url and request_timeout. */
class GeminiTransport
{
    public const MODELS = ['gemini-2.5-flash-lite', 'gemini-flash-lite-latest', 'gemini-2.5-flash', 'gemini-flash-latest'];

    public function generate(string $model, string $instruction, array $contents, array $tools = []): Content
    {
        if (! config('gemini.api_key')) {
            throw new RuntimeException('AI unavailable.');
        }
        $client = Gemini::generativeModel(model: $model)
            ->withSystemInstruction(Content::parse($instruction))
            ->withGenerationConfig(new GenerationConfig(maxOutputTokens: 2048));
        foreach ($tools as $tool) {
            $client = $client->withTool($tool);
        }
        $response = $client->generateContent(...$contents);
        $candidate = $response->candidates[0] ?? null;
        if (! $candidate || $candidate->finishReason !== FinishReason::STOP || ! $candidate->content?->parts) {
            throw new RuntimeException('AI returned no complete answer.');
        }

        // Preserve the entire native content, including function IDs and thought signatures.
        return $candidate->content;
    }

    public static function text(Content $content): string
    {
        return trim(implode("\n", array_map(fn ($part) => $part->thought ? '' : ($part->text ?? ''), $content->parts)));
    }
}
