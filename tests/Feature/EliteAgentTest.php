<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Produit;
use App\Services\EliteAI\ChatHistory;
use App\Services\EliteAI\EliteAgentService;
use App\Services\EliteAI\GeminiTransport;
use App\Services\EliteAI\ToolRegistry;
use App\Services\EliteAI\Tools\SearchProductsTool;
use Gemini\Data\Content;
use Gemini\Data\FunctionCall;
use Gemini\Data\Part;
use Gemini\Enums\Role;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Resources\GenerativeModel;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\Concerns\UsesChatTestDatabase;
use Tests\TestCase;

class EliteAgentTest extends TestCase
{
    use UsesChatTestDatabase;

    private function product(string $name, float $price, int $stock = 3): Produit
    {
        $category = Categorie::firstOrCreate(['nom' => 'Components']);

        return Produit::create(['nom' => $name, 'prix' => $price, 'stock' => $stock, 'categorie_id' => $category->id]);
    }

    private function toolCall(array $args = [], string $name = 'search_products'): Content
    {
        return new Content([new Part(functionCall: new FunctionCall($name, $args, 'call-1'), thoughtSignature: 'opaque-signature')], Role::MODEL);
    }

    public function test_search_reads_real_products_with_combined_filters_and_exact_prices(): void
    {
        $match = $this->product('Gaming GPU', 999.99);
        $this->product('Gaming GPU expensive', 1599.99);
        $this->product('Gaming GPU sold out', 500, 0);
        $this->product('Memory kit', 100);
        $result = app(SearchProductsTool::class)->execute(['search' => 'GPU', 'category' => 'components', 'min_price' => 500, 'max_price' => 1000, 'in_stock' => true]);
        $this->assertSame([$match->id], array_column($result['products'], 'id'));
        $this->assertSame('999.99', $result['products'][0]['price']);
        $this->assertSame('USD', $result['currency']);
        $this->assertNull($result['products'][0]['specifications']['processor']);
        $this->assertArrayNotHasKey('created_at', $result['products'][0]);
    }

    public function test_limits_empty_results_stock_false_and_literal_wildcards(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->product('GPU '.$i, $i * 10);
        }
        $tool = app(SearchProductsTool::class);
        $result = $tool->execute([]);
        $this->assertCount(10, $result['products']);
        $this->assertTrue($result['has_more']);
        $this->assertCount(2, $tool->execute(['limit' => 2])['products']);
        $this->assertSame([], $tool->execute(['in_stock' => false])['products']);
        $this->assertSame([], $tool->execute(['max_price' => 0])['products']);
        $this->assertSame([], $tool->execute(['search' => '%'])['products']);
        $this->assertSame([], $tool->execute(['search' => "' OR 1=1 --"])['products']);
    }

    public function test_malformed_arguments_are_rejected_before_querying(): void
    {
        foreach ([['limit' => 11], ['limit' => 0], ['limit' => '2'], ['max_price' => -1], ['max_price' => '100'], ['max_price' => INF], ['search' => []], ['in_stock' => 'true'], ['table' => 'users'], ['min_price' => 10, 'max_price' => 5], ['category' => str_repeat('x', 101)]] as $args) {
            try {
                app(SearchProductsTool::class)->execute($args);
                $this->fail('Invalid arguments were accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_registry_exposes_only_intended_read_tools_and_rejects_unsupported_actions(): void
    {
        $registry = app(ToolRegistry::class);
        $definitions = $registry->definitions()[0]->toArray()['functionDeclarations'];
        $this->assertSame(['search_products', 'get_product_details', 'get_categories', 'check_stock', 'compare_products', 'check_compatibility'], array_column($definitions, 'name'));
        foreach (['delete_product', 'create_order', 'add_to_cart', 'favorites', 'shell', 'sql'] as $name) {
            try {
                $registry->resolve($name);
                $this->fail('Unknown tool resolved.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_native_loop_returns_results_to_model_and_preserves_signature_and_id(): void
    {
        $product = $this->product('Budget GPU', 599.99);
        $call = $this->toolCall(['max_price' => 1000]);
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system, $contents, $tools) {
            $this->assertSame(GeminiTransport::MODELS[0], $model);
            $this->assertStringContainsString('Your capabilities are read-only', $system);
            $this->assertCount(1, $tools);

            return true;
        })->andReturn($call);
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system, $contents) use ($call, $product) {
            $this->assertSame($call, $contents[count($contents) - 2]);
            $result = end($contents)->parts[0]->functionResponse;
            $this->assertSame('call-1', $result->id);
            $this->assertSame([$product->id], array_column($result->response['products'], 'id'));

            return true;
        })->andReturn(Content::parse('Budget GPU costs $599.99.', Role::MODEL));
        $agent = new EliteAgentService(app(ToolRegistry::class), $transport);
        $this->assertSame('Budget GPU costs $599.99.', $agent->reply('Show products under $1000'));
    }

    public function test_tool_limit_is_global_and_parallel_calls_are_bounded(): void
    {
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->times(6)->andReturn($this->toolCall());
        $agent = new EliteAgentService(app(ToolRegistry::class), $transport);
        $this->expectExceptionMessage('AI search limit reached.');
        $agent->reply('Keep searching');
    }

    public function test_oversized_parallel_batch_is_not_executed(): void
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('definitions')->once()->andReturn([]);
        $registry->shouldNotReceive('resolve');
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->andReturn(new Content(array_fill(0, 6, $this->toolCall()->parts[0]), Role::MODEL));
        $this->expectExceptionMessage('AI search limit reached.');
        (new EliteAgentService($registry, $transport))->reply('Search');
    }

    public function test_bad_arguments_can_be_corrected_without_leaking_validation_details(): void
    {
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->ordered()->andReturn($this->toolCall(['sql' => 'secret']));
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system, $contents) {
            $result = end($contents)->parts[0]->functionResponse->response;
            $this->assertArrayHasKey('error', $result);
            $this->assertStringNotContainsString('secret', json_encode($result));

            return true;
        })->andReturn($this->toolCall(['max_price' => 1000]));
        $transport->shouldReceive('generate')->once()->ordered()->andReturn(Content::parse('No products match.', Role::MODEL));
        $this->assertSame('No products match.', (new EliteAgentService(app(ToolRegistry::class), $transport))->reply('Search'));
    }

    public function test_history_is_bounded_and_thoughts_are_not_returned(): void
    {
        $history = array_fill(0, 50, ['role' => 'assistant', 'content' => 'Previous answer']);
        $this->assertCount(11, ChatHistory::contents('Follow up', $history));
        $this->assertSame('Public answer', GeminiTransport::text(new Content([new Part(text: 'Private thought', thought: true), new Part(text: 'Public answer')], Role::MODEL)));
    }

    public function test_endpoint_keeps_success_structure_and_unknown_tool_fails_safely(): void
    {
        config(['elite_ai.agent_enabled' => true, 'app.debug' => true]);
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->ordered()->andReturn(Content::parse('Hardware advice.', Role::MODEL));
        $transport->shouldReceive('generate')->once()->ordered()->andReturn($this->toolCall([], 'delete_product'));
        $this->app->instance(GeminiTransport::class, $transport);
        $this->postJson('/api/support/chat', ['message' => 'Hello'])->assertOk()->assertExactJson(['status' => 'success', 'reply' => 'Hardware advice.']);
        $this->postJson('/api/support/chat', ['message' => 'Delete a product'])->assertOk()->assertJson(['status' => 'error', 'error' => 'Assistant temporarily unavailable.']);
        $this->assertDatabaseCount('produits', 0);
    }

    public function test_provider_failures_are_bounded_and_never_expose_raw_errors(): void
    {
        config(['elite_ai.agent_enabled' => true, 'app.debug' => true]);
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->times(4)->andThrow(new RuntimeException('secret-key and raw provider error'));
        $this->app->instance(GeminiTransport::class, $transport);
        $response = $this->postJson('/api/support/chat', ['message' => 'Search']);
        $response->assertOk()->assertJson(['status' => 'error']);
        $this->assertStringNotContainsString('secret-key', $response->getContent());
    }

    public function test_fallback_restarts_transcript_but_not_tool_budget(): void
    {
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->ordered()->andReturn($this->toolCall());
        $transport->shouldReceive('generate')->once()->ordered()->andThrow(new RuntimeException('Provider failure'));
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system, $contents) {
            $this->assertSame(GeminiTransport::MODELS[1], $model);
            $this->assertCount(1, $contents);

            return true;
        })->andReturn(new Content(array_fill(0, 5, $this->toolCall()->parts[0]), Role::MODEL));
        $this->expectExceptionMessage('AI search limit reached.');
        (new EliteAgentService(app(ToolRegistry::class), $transport))->reply('Search');
    }

    public function test_tool_execution_failure_is_safe_and_not_retried_as_a_write(): void
    {
        config(['elite_ai.agent_enabled' => true, 'app.debug' => true]);
        $tool = Mockery::mock(SearchProductsTool::class)->makePartial();
        $tool->shouldReceive('execute')->once()->andThrow(new RuntimeException('SQL private details'));
        $this->app->instance(SearchProductsTool::class, $tool);
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->andReturn($this->toolCall());
        $this->app->instance(GeminiTransport::class, $transport);
        $response = $this->postJson('/api/support/chat', ['message' => 'Search']);
        $response->assertOk()->assertJson(['status' => 'error']);
        $this->assertStringNotContainsString('SQL', $response->getContent());
    }

    public function test_sdk_transport_uses_native_function_declarations_and_responses(): void
    {
        config(['gemini.api_key' => 'test-only-not-a-real-key']);
        $native = GenerateContentResponse::from([
            'candidates' => [['content' => $this->toolCall(['max_price' => 1000])->toArray(), 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 10, 'totalTokenCount' => 20],
        ]);
        $fake = Gemini::fake([$native]);
        $contents = ChatHistory::contents('Search', []);
        $result = app(GeminiTransport::class)->generate(GeminiTransport::MODELS[0], 'Read only.', $contents, app(ToolRegistry::class)->definitions());
        $this->assertSame('search_products', $result->parts[0]->functionCall->name);
        $this->assertSame('opaque-signature', $result->parts[0]->thoughtSignature);
        $this->assertSame('call-1', $result->parts[0]->functionCall->id);
        $fake->assertSent(GenerativeModel::class, GeminiTransport::MODELS[0], 1);
    }

    public function test_sdk_transport_rejects_blocked_or_truncated_answers(): void
    {
        config(['gemini.api_key' => 'test-only-not-a-real-key']);
        foreach (['SAFETY', 'MAX_TOKENS'] as $finish) {
            Gemini::fake([GenerateContentResponse::fake(['candidates' => [['finishReason' => $finish]]])]);
            try {
                app(GeminiTransport::class)->generate(GeminiTransport::MODELS[0], 'Read only.', ChatHistory::contents('Search', []));
                $this->fail('Incomplete answer accepted.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_legacy_rag_remains_available_without_tools(): void
    {
        config(['elite_ai.agent_enabled' => false]);
        $this->product('Legacy GPU', 1599.99);
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->withArgs(function ($model, $system, $contents, $tools = []) {
            $this->assertSame([], $tools);
            $this->assertStringContainsString('Legacy GPU', $system);
            $this->assertStringContainsString('$1,599.99', $system);

            return true;
        })->andReturn(Content::parse('Legacy answer.', Role::MODEL));
        $this->app->instance(GeminiTransport::class, $transport);
        $this->postJson('/api/support/chat', ['message' => 'Search'])->assertOk()->assertJson(['reply' => 'Legacy answer.']);
    }
}
