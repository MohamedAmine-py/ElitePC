<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SupportChatRequest — validates and sanitizes incoming AI chat messages.
 *
 * Security measures:
 * - Message length capped at 2000 characters to prevent prompt-injection bloat.
 * - Conversation history limited to 10 entries to prevent payload abuse.
 * - HTML tags stripped from the user message in prepareForValidation().
 * - Role field whitelisted to 'user', 'model', or 'assistant' only.
 */
class SupportChatRequest extends FormRequest
{
    public const HISTORY_LIMIT = 10;

    /**
     * All users (authenticated or guest) can use the support chat.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitize inputs before validation runs.
     * Strips HTML/script tags from the user message to prevent XSS injection.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('message')) {
            $this->merge([
                'message' => strip_tags($this->input('message')),
            ]);
        }
    }

    /**
     * Validation rules for the chat endpoint.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array|max:'.self::HISTORY_LIMIT,
            'history.*.role' => 'required|string|in:user,model,assistant',
            'history.*.content' => 'required|string|max:5000',
        ];
    }

    /**
     * Custom error messages for clarity.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Please enter a message to send to the assistant.',
            'message.max' => 'Your message exceeds the maximum length of 2000 characters.',
            'history.max' => 'Conversation history may contain at most '.self::HISTORY_LIMIT.' recent messages.',
            'history.*.role.in' => 'Invalid message role in conversation history.',
        ];
    }
}
