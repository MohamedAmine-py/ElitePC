<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Produit;
use App\Models\User;
use App\Services\EliteAI\ChatHistory;
use App\Services\EliteAI\EliteAgentService;
use App\Services\EliteAI\GeminiTransport;
use App\Services\EliteAI\KnowledgeRetriever;
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

    public function test_exhaustive_search_reads_the_whole_live_catalog_after_it_grows(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->product('Catalog item '.$i, $i * 10, $i === 16 ? 0 : 3);
        }
        $tool = app(SearchProductsTool::class);
        $result = $tool->execute(['all' => true]);
        $this->assertSame(Produit::orderBy('prix')->orderBy('id')->pluck('id')->all(), array_column($result['products'], 'id'));
        $this->assertSame(16, $result['returned_count']);
        $this->assertFalse($result['has_more']);
        $this->assertSame(0, $result['products'][15]['stock']);

        for ($i = 17; $i <= 20; $i++) {
            $this->product('Catalog item '.$i, $i * 10);
        }
        $grown = $tool->execute(['all' => true]);
        $this->assertSame(Produit::count(), $grown['returned_count']);
        $this->assertSame(20, $grown['returned_count']);
        $this->assertSame(Produit::orderBy('prix')->orderBy('id')->pluck('id')->all(), array_column($grown['products'], 'id'));
        $this->assertFalse($grown['has_more']);
        $this->assertCount(10, $tool->execute([])['products']);
        $this->assertCount(10, $tool->execute(['all' => false])['products']);
        $this->assertTrue($tool->execute([])['has_more']);
    }

    public function test_exhaustive_search_keeps_explicit_filters_and_ignores_the_row_limit(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            $this->product('GPU '.$i, $i * 10);
        }
        $this->product('CPU', 50);
        $this->product('GPU sold out', 50, 0);
        $tool = app(SearchProductsTool::class);
        $args = ['all' => true, 'search' => 'GPU', 'category' => 'components', 'min_price' => 20, 'in_stock' => true, 'limit' => 2];
        $result = $tool->execute($args);
        $this->assertCount(12, $result['products']);
        $this->assertSame('20.00', $result['products'][0]['price']);
        $this->assertFalse($result['has_more']);
        $args['all'] = false;
        $this->assertCount(2, $tool->execute($args)['products']);
        $this->assertSame([], $tool->execute(['all' => true, 'search' => 'not present'])['products']);
        $this->assertFalse($tool->execute(['all' => true, 'search' => 'not present'])['has_more']);
    }

    public function test_agent_can_recover_a_partial_search_for_an_explicit_all_products_table(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->product('Catalog item '.$i, $i * 10);
        }
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system, $contents, $tools) {
            $this->assertStringContainsString('call search_products with all=true', $system);
            $this->assertStringContainsString('Product | Category | Price | Stock', $system);
            $schema = $tools[0]->toArray()['functionDeclarations'][0]['parameters'];
            $this->assertSame('BOOLEAN', $schema['properties']['all']['type']);

            return true;
        })->andReturn($this->toolCall());
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system, $contents) {
            $result = end($contents)->parts[0]->functionResponse->response;
            $this->assertSame(10, $result['returned_count']);
            $this->assertTrue($result['has_more']);

            return true;
        })->andReturn($this->toolCall(['all' => true]));
        $transport->shouldReceive('generate')->once()->ordered()->andReturnUsing(function ($model, $system, $contents) {
            $result = end($contents)->parts[0]->functionResponse->response;
            $this->assertSame(Produit::count(), $result['returned_count']);
            $this->assertFalse($result['has_more']);
            $rows = array_map(fn ($p) => "| {$p['name']} | {$p['category']} | \${$p['price']} | {$p['stock']} |", $result['products']);

            return Content::parse("| Product | Category | Price | Stock |\n| --- | --- | --- | --- |\n".implode("\n", $rows), Role::MODEL);
        });
        $reply = (new EliteAgentService(app(ToolRegistry::class), $transport, app(KnowledgeRetriever::class)))
            ->reply('give me a table for all products');
        $this->assertSame(20, substr_count($reply, '| Catalog item '));
    }

    public function test_malformed_arguments_are_rejected_before_querying(): void
    {
        foreach ([['all' => 'true'], ['all' => 1], ['all' => null], ['limit' => 11], ['limit' => 0], ['limit' => '2'], ['max_price' => -1], ['max_price' => '100'], ['max_price' => INF], ['search' => []], ['in_stock' => 'true'], ['table' => 'users'], ['min_price' => 10, 'max_price' => 5], ['category' => str_repeat('x', 101)]] as $args) {
            try {
                app(SearchProductsTool::class)->execute($args);
                $this->fail('Invalid arguments were accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_registry_exposes_only_intended_tools_and_rejects_unsupported_actions(): void
    {
        $registry = app(ToolRegistry::class);
        $definitions = $registry->definitions()[0]->toArray()['functionDeclarations'];
        $this->assertSame(['search_products', 'get_product_details', 'get_categories', 'check_stock', 'compare_products', 'check_compatibility'], array_column($definitions, 'name'));
        foreach (['set_cart_quantity', 'remove_from_cart', 'add_favorite', 'remove_favorite', 'delete_product', 'create_order', 'add_to_cart', 'favorites', 'shell', 'sql'] as $name) {
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
            $this->assertStringContainsString('Elite AI is READ ONLY', $system);
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
        $agent = new EliteAgentService(app(ToolRegistry::class), $transport, app(KnowledgeRetriever::class));
        $this->assertSame('Budget GPU costs $599.99.', $agent->reply('Show products under $1000'));
    }

    public function test_tool_limit_is_global_and_parallel_calls_are_bounded(): void
    {
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->times(6)->andReturn($this->toolCall());
        $agent = new EliteAgentService(app(ToolRegistry::class), $transport, app(KnowledgeRetriever::class));
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
        (new EliteAgentService($registry, $transport, app(KnowledgeRetriever::class)))->reply('Search');
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
        $this->assertSame('No products match.', (new EliteAgentService(app(ToolRegistry::class), $transport, app(KnowledgeRetriever::class)))->reply('Search'));
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
        (new EliteAgentService(app(ToolRegistry::class), $transport, app(KnowledgeRetriever::class)))->reply('Search');
    }

    public function test_tool_execution_failure_is_safe(): void
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

    public function test_chat_is_public_and_does_not_forward_identity_or_replay_requests(): void
    {
        config(['elite_ai.agent_enabled' => true]);
        $user = User::create([
            'nom' => 'Private customer name', 'email' => 'private-customer@example.test',
            'mot_de_passe' => bcrypt('password'), 'role' => 'client',
        ]);
        $token = $user->createToken('test')->plainTextToken;
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->times(4)->withArgs(function ($model, $system, $contents, $tools) use ($user, $token) {
            $payload = json_encode([$system, $contents, $tools]);
            foreach ([$user->nom, $user->email, $token, 'user_id', 'execution_id'] as $privateValue) {
                $this->assertStringNotContainsString($privateValue, $payload);
            }
            $this->assertCount(6, $tools[0]->toArray()['functionDeclarations']);

            return true;
        })->andReturn(
            Content::parse('Answer 1', Role::MODEL), Content::parse('Answer 2', Role::MODEL),
            Content::parse('Answer 3', Role::MODEL), Content::parse('Answer 4', Role::MODEL),
        );
        $this->app->instance(GeminiTransport::class, $transport);
        // Obsolete headers are ignored, even when repeated or not UUIDs.
        $headers = ['X-Chat-Request-ID' => 'obsolete-client-value'];
        $input = ['message' => 'Catalog question'];
        $this->postJson('/api/support/chat', $input, $headers)->assertOk()
            ->assertExactJson(['status' => 'success', 'reply' => 'Answer 1'])
            ->assertHeaderMissing('X-Chat-Execution-ID');
        $this->postJson('/api/support/chat', $input, $headers)->assertOk()
            ->assertJsonPath('reply', 'Answer 2')->assertHeaderMissing('X-Chat-Execution-ID');
        $this->actingAs($user, 'web');
        $this->postJson('/api/support/chat', $input, ['Authorization' => 'Bearer '.$token])->assertOk()
            ->assertJsonPath('reply', 'Answer 3')->assertHeaderMissing('X-Chat-Execution-ID');
        $this->postJson('/api/support/chat', $input, ['Authorization' => 'Bearer invalid'])->assertOk()
            ->assertJsonPath('reply', 'Answer 4')->assertHeaderMissing('X-Chat-Execution-ID');
    }

    public function test_public_chat_rejects_client_identity_and_history_metadata(): void
    {
        $agent = Mockery::mock(EliteAgentService::class);
        $agent->shouldNotReceive('reply');
        $this->app->instance(EliteAgentService::class, $agent);
        foreach (['user_id' => 7, 'user' => ['id' => 7], 'context' => ['user_id' => 7], 'execution_id' => 'client-chosen'] as $key => $value) {
            $this->postJson('/api/support/chat', ['message' => 'Question', $key => $value])->assertUnprocessable();
        }
        $this->postJson('/api/support/chat', ['message' => 'Question', 'user_id' => null])->assertUnprocessable();
        $this->postJson('/api/support/chat', [
            'message' => 'Question', 'history' => [['role' => 'user', 'content' => 'Hello', 'user_id' => 7]],
        ])->assertUnprocessable();
    }

    public function test_removed_mutations_are_rejected_without_changing_store_state(): void
    {
        config(['elite_ai.agent_enabled' => true]);
        $product = $this->product('Protected GPU', 99.99);
        $transport = Mockery::mock(GeminiTransport::class);
        foreach (['set_cart_quantity', 'remove_from_cart', 'add_favorite', 'remove_favorite'] as $name) {
            $transport->shouldReceive('generate')->once()->ordered()
                ->andReturn($this->toolCall(['product_id' => $product->id, 'quantity' => 2], $name));
        }
        $this->app->instance(GeminiTransport::class, $transport);
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/support/chat', ['message' => 'Change store data'])->assertOk()
                ->assertJsonPath('status', 'error')->assertHeaderMissing('X-Chat-Execution-ID');
        }
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseCount('commandes', 0);
        $this->assertSame(3, $product->fresh()->stock);
    }
}
