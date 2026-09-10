<?php

namespace App\Services\EliteAI;

use App\Http\Requests\SupportChatRequest;
use Gemini\Data\Content;
use Gemini\Enums\Role;
use Illuminate\Support\Facades\Validator;

class ChatHistory
{
    public static function contents(string $message, array $history): array
    {
        $history = array_values(array_slice($history, -SupportChatRequest::HISTORY_LIMIT));
        Validator::make(['message' => $message, 'history' => $history], (new SupportChatRequest)->rules())->validate();
        $contents = array_map(fn (array $entry) => Content::parse(
            strip_tags($entry['content']),
            $entry['role'] === 'user' ? Role::USER : Role::MODEL,
        ), $history);
        $contents[] = Content::parse($message);

        return $contents;
    }
}
