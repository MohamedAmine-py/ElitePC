<?php

namespace Tests\Feature;

use App\Http\Requests\SupportChatRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SupportChatHistoryTest extends TestCase
{
    public function test_ten_recent_history_messages_are_accepted(): void
    {
        $validator = Validator::make([
            'message' => 'Which of those is cheaper?',
            'history' => $this->history(SupportChatRequest::HISTORY_LIMIT),
        ], (new SupportChatRequest)->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_history_beyond_the_conversation_window_is_rejected(): void
    {
        $validator = Validator::make([
            'message' => 'Which of those is cheaper?',
            'history' => $this->history(SupportChatRequest::HISTORY_LIMIT + 1),
        ], (new SupportChatRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('history', $validator->errors()->toArray());
    }

    private function history(int $count): array
    {
        return array_map(fn (int $index) => [
            'role' => $index % 2 === 0 ? 'user' : 'model',
            'content' => $index % 2 === 0
                ? 'Recommend a gaming GPU.'
                : 'The catalog options are the RTX models currently in stock.',
        ], range(0, $count - 1));
    }
}
