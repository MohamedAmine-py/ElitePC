<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Produit;
use App\Services\EliteAI\EliteAgentService;
use App\Services\EliteAI\GeminiTransport;
use App\Services\EliteAI\KnowledgeRetriever;
use App\Services\EliteAI\ToolRegistry;
use Gemini\Data\Content;
use Gemini\Data\FunctionCall;
use Gemini\Data\Part;
use Gemini\Enums\Role;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\UsesChatTestDatabase;
use Tests\TestCase;

class KnowledgeAgentIntegrationTest extends TestCase
{
    use UsesChatTestDatabase;

    private const TOOL_NAMES = [
        'search_products', 'get_product_details', 'get_categories',
        'check_stock', 'compare_products', 'check_compatibility',
    ];

    public static function knowledgeQuestions(): array
    {
        return [
            'guest cart' => ['How does the guest cart work?', 'store.md', 'Guest and Account Carts'],
            'RAM' => ['Is 16GB enough for gaming?', 'hardware.md', 'RAM'],
            '1440p' => ['What should I prioritize for 1440p?', 'buying-guide.md', '1440p Gaming'],
            'general compatibility' => ['Can DDR5 RAM work in a DDR4 motherboard?', 'compatibility.md', 'RAM Compatibility'],
        ];
    }

    #[DataProvider('knowledgeQuestions')]
    public function test_relevant_sections_reach_the_public_chat_prompt(string $question, string $source, string $section): void
    {
        config(['elite_ai.agent_enabled' => true]);
        $chunks = app(KnowledgeRetriever::class)->retrieve($question);
        $this->assertNotEmpty($chunks);
        $registry = Mockery::mock(ToolRegistry::class)->makePartial();
        $registry->shouldReceive('definitions')->once()->andReturn(app(ToolRegistry::class)->definitions());
        $registry->shouldNotReceive('resolve'); // Informational answer has no tool execution or mutations.
        $this->app->instance(ToolRegistry::class, $registry);
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->withArgs(function ($model, $system, $contents, $tools) use ($question, $source, $section, $chunks) {
            $context = substr($system, strpos($system, '[ELITEPC KNOWLEDGE]'));
            $this->assertStringContainsString("Source: $source", $context);
            $this->assertStringContainsString("Section: $section\n", $context);
            $this->assertSame(count($chunks), substr_count($context, 'Source: '));
            $this->assertLessThanOrEqual(3, count($chunks));
            foreach ($chunks as $chunk) {
                $this->assertStringContainsString($chunk['content'], $context);
                $this->assertArrayHasKey('score', $chunk);
            }
            foreach (['Score:', 'coverage', 'matched_terms', 'Section: Browsing and Searching the Catalog'] as $unwanted) {
                $this->assertStringNotContainsString($unwanted, $context);
            }
            $this->assertSame($question, end($contents)->parts[0]->text);
            $this->assertSame(self::TOOL_NAMES, array_column($tools[0]->toArray()['functionDeclarations'], 'name'));
            $this->assertStringContainsString('do not append read-only disclaimers', $system);
            $this->assertStringContainsString('Answer general compatibility principles', $system);
            $this->assertStringContainsString('Keep internal grounding invisible', $system);
            $this->assertStringContainsString('Do not automatically append an offer', $system);
            $this->assertStringContainsString('Ask a follow-up only if clarification is needed', $system);
            $this->assertStringContainsString('Present product comparisons in a concise Markdown table', $system);
            $this->assertStringContainsString('such as Add to Cart', $system);
            $this->assertStringContainsString('do not embellish with unsupported technical details', $system);

            return true;
        })->andReturn(Content::parse('Grounded answer.', Role::MODEL));
        $this->app->instance(GeminiTransport::class, $transport);

        $this->postJson('/api/support/chat', ['message' => $question])
            ->assertOk()->assertJson(['status' => 'success', 'reply' => 'Grounded answer.']);
    }

    public static function unmatchedQuestions(): array
    {
        return [
            'undefined policy' => ['Do you offer a warranty?'],
            'unrelated topic' => ['Explain ancient Egyptian hieroglyphs.'],
        ];
    }

    #[DataProvider('unmatchedQuestions')]
    public function test_no_match_adds_no_context_and_keeps_policy_grounding(string $question): void
    {
        $this->assertSame([], app(KnowledgeRetriever::class)->retrieve($question));
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->withArgs(function ($model, $system) {
            $this->assertStringNotContainsString('[ELITEPC KNOWLEDGE]', $system);
            $this->assertStringContainsString('Never invent ElitePC policies.', $system);
            $this->assertStringContainsString('available ElitePC information does not define that policy', $system);
            $this->assertStringContainsString('Absence of a policy is not evidence that the store does not offer it.', $system);

            return true;
        })->andReturn(Content::parse('No verified information.', Role::MODEL));

        $this->assertSame('No verified information.', $this->agent($transport)->reply($question));
    }

    public function test_retrieval_uses_only_current_message_once_and_survives_model_fallback(): void
    {
        $question = 'Is 16GB enough for gaming?';
        $retriever = Mockery::mock(KnowledgeRetriever::class);
        $retriever->shouldReceive('retrieve')->once()->with($question)
            ->andReturn(app(KnowledgeRetriever::class)->retrieve($question));
        $transport = Mockery::mock(GeminiTransport::class);
        $firstSystem = null;
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system) use (&$firstSystem) {
            $this->assertSame(GeminiTransport::MODELS[0], $model);
            $firstSystem = $system;

            return true;
        })->andThrow(new RuntimeException('Provider unavailable'));
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system, $contents) use (&$firstSystem) {
            $this->assertSame(GeminiTransport::MODELS[1], $model);
            $this->assertSame($firstSystem, $system);
            $this->assertStringNotContainsString('Section: Guest and Account Carts', $system);
            $this->assertCount(2, $contents);

            return true;
        })->andReturn(Content::parse('RAM guidance.', Role::MODEL));

        $agent = new EliteAgentService(app(ToolRegistry::class), $transport, $retriever);
        $this->assertSame('RAM guidance.', $agent->reply($question, [['role' => 'user', 'content' => 'How does the guest cart work?']]));
    }

    public function test_hybrid_request_keeps_knowledge_while_executing_live_catalog_tools(): void
    {
        $product = $this->product('Test Gaming PC', 1723.45);
        $question = 'Recommend a PC from your store for 1440p gaming.';
        $transport = Mockery::mock(GeminiTransport::class);
        $firstSystem = null;
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system) use (&$firstSystem) {
            $firstSystem = $system;
            $this->assertStringContainsString('Source: buying-guide.md', $system);
            $this->assertStringContainsString("Section: 1440p Gaming\n", $system);
            $this->assertStringContainsString('live catalog tools remain authoritative', $system);
            $this->assertStringNotContainsString('Test Gaming PC', $system);

            return true;
        })->andReturn($this->toolCall('search_products', []));
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system, $contents) use (&$firstSystem, $product) {
            $this->assertSame($firstSystem, $system);
            $result = end($contents)->parts[0]->functionResponse->response;
            $this->assertSame($product->id, $result['products'][0]['id']);
            $this->assertSame('1723.45', $result['products'][0]['price']);

            return true;
        })->andReturn($this->toolCall('get_product_details', ['product_id' => $product->id]));
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system, $contents) use (&$firstSystem, $product) {
            $this->assertSame($firstSystem, $system);
            $result = end($contents)->parts[0]->functionResponse->response;
            $this->assertSame($product->id, $result['product']['id']);
            $this->assertSame('1723.45', $result['product']['price']);

            return true;
        })->andReturn(Content::parse('Recommendation using verified product facts.', Role::MODEL));

        $this->assertSame('Recommendation using verified product facts.', $this->agent($transport)->reply($question));
    }

    public function test_general_knowledge_does_not_replace_insufficient_product_evidence(): void
    {
        $ram = $this->product('DDR5 RAM');
        $board = $this->product('DDR4 motherboard');
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->ordered()
            ->andReturn($this->toolCall('check_compatibility', ['product_ids' => [$ram->id, $board->id]]));
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system, $contents) {
            $this->assertStringContainsString('Section: RAM Compatibility', $system);
            $this->assertStringContainsString('Retrieved knowledge must not override insufficient_data', $system);
            $this->assertSame('insufficient_data', end($contents)->parts[0]->functionResponse->response['status']);

            return true;
        })->andReturn(Content::parse("I don't have enough catalog data to confirm that compatibility.", Role::MODEL));

        $this->assertSame("I don't have enough catalog data to confirm that compatibility.",
            $this->agent($transport)->reply('Is this DDR5 RAM compatible with this DDR4 motherboard?'));
    }

    private function agent(GeminiTransport $transport): EliteAgentService
    {
        return new EliteAgentService(app(ToolRegistry::class), $transport, app(KnowledgeRetriever::class));
    }

    private function toolCall(string $name, array $arguments): Content
    {
        return new Content([new Part(functionCall: new FunctionCall($name, $arguments, 'catalog-call'))], Role::MODEL);
    }

    private function product(string $name, float $price = 100): Produit
    {
        $category = Categorie::firstOrCreate(['nom' => 'Components']);

        return Produit::create(['nom' => $name, 'prix' => $price, 'stock' => 3, 'categorie_id' => $category->id]);
    }
}
