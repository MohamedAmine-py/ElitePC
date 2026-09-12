<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Produit;
use App\Models\User;
use App\Services\EliteAI\AgentContext;
use App\Services\EliteAI\EliteAgentService;
use App\Services\EliteAI\GeminiTransport;
use App\Services\EliteAI\ToolExecutor;
use App\Services\EliteAI\ToolRegistry;
use Gemini\Data\Content;
use Gemini\Enums\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\UsesChatTestDatabase;
use Tests\TestCase;

class ChatAuthenticationTest extends TestCase
{
    use UsesChatTestDatabase;

    private function user(): User
    {
        return User::create(['nom' => 'Private test customer', 'email' => uniqid().'@example.test', 'mot_de_passe' => bcrypt('password'), 'role' => 'client']);
    }

    private function chat(?string $token = null, array $input = ['message' => 'Catalog question'], ?string $id = null)
    {
        Auth::forgetGuards();
        $headers = $token === null ? [] : ['Authorization' => 'Bearer '.$token];
        if ($id !== null) {
            $headers['X-Chat-Request-ID'] = $id;
        }

        return $this->postJson('/api/support/chat', $input, $headers);
    }

    private function expectUser(?User $expected, int $times = 1): void
    {
        config(['elite_ai.agent_enabled' => true]);
        $agent = Mockery::mock(EliteAgentService::class);
        $agent->shouldReceive('reply')->times($times)->withArgs(function ($message, $history, AgentContext $context) use ($expected) {
            $this->assertTrue(Str::isUuid($context->executionId));
            $this->assertSame($expected?->id, $context->user?->id);

            return true;
        })->andReturn('Catalog answer.');
        $this->app->instance(EliteAgentService::class, $agent);
    }

    public function test_guest_chat_has_server_execution_identity_and_no_user(): void
    {
        $this->expectUser(null);
        $response = $this->chat()->assertOk()->assertExactJson(['status' => 'success', 'reply' => 'Catalog answer.']);
        $this->assertTrue(Str::isUuid($response->headers->get('X-Chat-Execution-ID')));
    }

    public function test_bearer_resolves_user_and_never_uses_client_identity(): void
    {
        $a = $this->user();
        $b = $this->user();
        $token = $a->createToken('test')->plainTextToken;
        $this->expectUser($a);
        foreach (['user_id' => $b->id, 'user' => ['id' => $b->id], 'context' => ['user_id' => $b->id], 'execution_id' => (string) Str::uuid()] as $key => $value) {
            $this->chat($token, ['message' => 'Question', $key => $value])->assertUnprocessable();
        }
        $this->chat($token, ['message' => 'Question', 'user_id' => null])->assertUnprocessable();
        $this->chat($token)->assertOk();
    }

    public function test_invalid_expired_and_revoked_tokens_return_401_before_agent(): void
    {
        $agent = Mockery::mock(EliteAgentService::class);
        $agent->shouldNotReceive('reply');
        $this->app->instance(EliteAgentService::class, $agent);
        $user = $this->user();
        $expired = $user->createToken('expired', ['*'], now()->subMinute())->plainTextToken;
        $revoked = $user->createToken('revoked');
        $revoked->accessToken->delete();
        foreach (['invalid', $expired, $revoked->plainTextToken] as $token) {
            $this->chat($token)->assertUnauthorized();
        }
        Auth::forgetGuards();
        $this->postJson('/api/support/chat', ['message' => 'Question'], ['Authorization' => 'Basic invalid'])->assertUnauthorized();
    }

    public function test_guest_identity_does_not_inherit_a_web_session(): void
    {
        $this->actingAs($this->user(), 'web');
        $this->expectUser(null);
        $this->postJson('/api/support/chat', ['message' => 'Question'])->assertOk();
    }

    public function test_invalid_bearer_cannot_fall_back_to_a_web_session(): void
    {
        $this->actingAs($this->user(), 'web');
        $this->postJson('/api/support/chat', ['message' => 'Question'], ['Authorization' => 'Bearer invalid'])->assertUnauthorized();
    }

    public function test_transport_replay_reuses_server_result_and_id_for_same_account_and_input(): void
    {
        $a = $this->user();
        $token = $a->createToken('test')->plainTextToken;
        $clientId = (string) Str::uuid();
        $this->expectUser($a);
        $first = $this->chat($token, id: $clientId)->assertOk();
        $second = $this->chat($token, id: strtoupper($clientId))->assertOk();
        $this->assertSame($first->json(), $second->json());
        $this->assertSame($first->headers->get('X-Chat-Execution-ID'), $second->headers->get('X-Chat-Execution-ID'));
        $this->assertNotSame($clientId, $first->headers->get('X-Chat-Execution-ID'));
        $this->chat($token, ['message' => 'Different input'], $clientId)->assertConflict();
        $this->chat($token, id: 'not-a-uuid')->assertUnprocessable();
    }

    public function test_same_client_id_is_not_authorization_or_cross_account_cache_access(): void
    {
        $a = $this->user();
        $b = $this->user();
        $clientId = (string) Str::uuid();
        $this->expectUser($a);
        $first = $this->chat($a->createToken('test')->plainTextToken, id: $clientId)->assertOk();
        $this->expectUser($b);
        $second = $this->chat($b->createToken('test')->plainTextToken, id: $clientId)->assertOk();
        $this->assertNotSame($first->headers->get('X-Chat-Execution-ID'), $second->headers->get('X-Chat-Execution-ID'));
        $this->chat('invalid', id: $clientId)->assertUnauthorized();
    }

    public function test_separate_requests_and_guest_retries_get_distinct_server_ids(): void
    {
        $this->expectUser(null, 2);
        $id = (string) Str::uuid();
        $a = $this->chat(id: $id)->assertOk();
        $b = $this->chat(id: $id)->assertOk();
        $this->assertNotSame($a->headers->get('X-Chat-Execution-ID'), $b->headers->get('X-Chat-Execution-ID'));
    }

    public function test_public_tool_schemas_and_results_are_identical_for_guest_and_account(): void
    {
        $category = Categorie::create(['nom' => 'Components']);
        $a = Produit::create(['nom' => 'GPU A', 'prix' => 12.34, 'stock' => 2, 'categorie_id' => $category->id]);
        $b = Produit::create(['nom' => 'GPU B', 'prix' => 25, 'stock' => 3, 'categorie_id' => $category->id]);
        $registry = app(ToolRegistry::class);
        $definitions = $registry->definitions()[0]->toArray();
        $this->assertStringNotContainsString('user_id', json_encode($definitions));
        $this->assertCount(6, $definitions['functionDeclarations']);
        $guest = new AgentContext;
        $account = new AgentContext($this->user());
        foreach ([
            'search_products' => [], 'get_categories' => [],
            'get_product_details' => ['product_id' => $a->id], 'check_stock' => ['product_id' => $a->id],
            'compare_products' => ['product_ids' => [$a->id, $b->id]], 'check_compatibility' => ['product_ids' => [$a->id, $b->id]],
        ] as $name => $arguments) {
            $executor = new ToolExecutor;
            $this->assertSame($executor->execute($registry->resolve($name), $arguments, $guest),
                $executor->execute($registry->resolve($name), $arguments, $account));
        }
    }

    public function test_authenticated_identity_is_not_in_provider_contents_or_prompt(): void
    {
        config(['elite_ai.agent_enabled' => true]);
        $a = $this->user();
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->withArgs(function ($model, $system, $contents, $tools) use ($a) {
            $json = json_encode([$system, $contents, $tools]);
            $this->assertStringNotContainsString($a->email, $json);
            $this->assertStringNotContainsString($a->nom, $json);
            $this->assertStringNotContainsString('user_id', $json);

            return true;
        })->andReturn(Content::parse('Catalog answer.', Role::MODEL));
        $this->app->instance(GeminiTransport::class, $transport);
        $this->chat($a->createToken('test')->plainTextToken)->assertOk()->assertJsonPath('status', 'success');
    }
}
