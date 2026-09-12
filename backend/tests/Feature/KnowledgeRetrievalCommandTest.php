<?php

namespace Tests\Feature;

use App\Services\EliteAI\GeminiTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class KnowledgeRetrievalCommandTest extends TestCase
{
    public function test_command_reports_local_results_without_database_or_provider_calls(): void
    {
        Http::preventStrayRequests();
        DB::shouldReceive('connection')->never();
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldNotReceive('generate');
        $this->app->instance(GeminiTransport::class, $transport);
        $this->artisan('elite-ai:retrieve', ['query' => 'How does the guest cart work?'])
            ->expectsOutput('Query: How does the guest cart work?')
            ->expectsOutput('1. store.md')
            ->expectsOutput('   Section: Guest and Account Carts')
            ->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_command_reports_missing_knowledge_matches(): void
    {
        $this->artisan('elite-ai:retrieve', ['query' => 'Do you offer a warranty?'])
            ->expectsOutput('No sufficiently relevant knowledge found.')
            ->assertSuccessful();
    }

    public function test_command_validates_limit(): void
    {
        $this->artisan('elite-ai:retrieve', ['query' => 'RAM', '--limit' => '0'])
            ->expectsOutput('Result limit must be between 1 and 10.')
            ->assertExitCode(2);
    }
}
