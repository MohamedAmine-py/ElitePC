<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EliteAI\AgentContext;
use App\Services\EliteAI\ChatExecutionStore;
use App\Services\EliteAI\EliteAgentService;
use App\Services\EliteAI\GeminiTransport;
use App\Services\EliteAI\ToolExecutor;
use App\Services\EliteAI\ToolRegistry;
use Gemini\Data\Content;
use Gemini\Data\FunctionCall;
use Gemini\Data\Part;
use Gemini\Enums\Role;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Fixtures\ContextProbeTool;
use Tests\TestCase;

class AgentReplayTest extends TestCase
{
    private function context(): AgentContext
    {
        $user = new User;
        $user->id = 7;

        return new AgentContext($user);
    }

    private function toolCall(array $args = ['product_id' => 1], ?string $id = 'call-1'): Content
    {
        return new Content([new Part(functionCall: new FunctionCall('context_probe', $args, $id))], Role::MODEL);
    }

    private function agent(ContextProbeTool $tool, GeminiTransport $transport): EliteAgentService
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('definitions')->andReturn([]);
        $registry->shouldReceive('resolve')->with('context_probe')->andReturn($tool);

        return new EliteAgentService($registry, $transport);
    }

    public function test_guest_cannot_execute_private_tools_even_via_legacy_contract(): void
    {
        $tool = new ContextProbeTool;
        foreach ([fn () => $tool->execute(['product_id' => 1]),
            fn () => $tool->executeWithContext(['product_id' => 1], new AgentContext),
            fn () => (new ToolExecutor)->execute($tool, ['product_id' => 1], new AgentContext)] as $execute) {
            try {
                $execute();
                $this->fail('Guest accessed private tool.');
            } catch (AuthenticationException) {
                $this->assertSame(0, $tool->executions);
            }
        }
    }

    public function test_context_reaches_tool_and_model_cannot_select_another_user(): void
    {
        $tool = new ContextProbeTool(false);
        $context = $this->context();
        $executor = new ToolExecutor;
        $executor->execute($tool, ['product_id' => 1], $context);
        $this->assertSame($context->user, $tool->seenUser);
        foreach ([['product_id' => 1, 'user_id' => 8], ['product_id' => 1, 'nested' => ['user_id' => 8]]] as $arguments) {
            try {
                $executor->execute($tool, $arguments, $context);
                $this->fail('Model identity accepted.');
            } catch (ValidationException) {
                $this->assertSame(1, $tool->executions);
            }
        }
    }

    public function test_repeated_private_reads_execute_normally(): void
    {
        $tool = new ContextProbeTool(false);
        $context = $this->context();
        $executor = new ToolExecutor;
        $executor->execute($tool, ['product_id' => 1], $context, 'same');
        $executor->execute($tool, ['product_id' => 1], $context, 'same');
        $this->assertSame(2, $tool->executions);
    }

    public function test_same_mutation_reuses_result_with_same_changed_or_missing_provider_id(): void
    {
        $tool = new ContextProbeTool;
        $context = $this->context();
        $executor = new ToolExecutor;
        $first = $executor->execute($tool, ['product_id' => 1], $context, 'same');
        foreach (['same', 'new-model-id', null] as $id) {
            $this->assertSame($first, $executor->execute($tool, ['product_id' => 1], $context, $id));
        }
        $this->assertSame(1, $tool->executions);
        $executor->execute($tool, ['product_id' => 1], $this->context(), 'same');
        $this->assertSame(2, $tool->executions);
    }

    public function test_changed_arguments_for_same_provider_id_fail_closed(): void
    {
        $tool = new ContextProbeTool;
        $context = $this->context();
        $executor = new ToolExecutor;
        $executor->execute($tool, ['product_id' => 1], $context, 'same');
        try {
            $executor->execute($tool, ['product_id' => 2], $context, 'same');
            $this->fail('Conflicting identity accepted.');
        } catch (RuntimeException) {
            $this->assertSame(1, $tool->executions);
        }
    }

    public function test_uncertain_operation_is_never_run_again(): void
    {
        $tool = new ContextProbeTool;
        $tool->fail = true;
        $context = $this->context();
        foreach (['first', 'retry'] as $id) {
            try {
                (new ToolExecutor)->execute($tool, ['product_id' => 1], $context, $id);
                $this->fail('Expected failure.');
            } catch (RuntimeException) {
                $this->assertSame(1, $tool->executions);
            }
        }
    }

    public function test_parallel_and_repeated_emissions_in_agent_loop_execute_mutation_once(): void
    {
        $tool = new ContextProbeTool;
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->ordered()->andReturn(new Content([$this->toolCall()->parts[0], $this->toolCall(id: 'another')->parts[0]], Role::MODEL));
        $transport->shouldReceive('generate')->once()->ordered()->andReturn($this->toolCall(id: null));
        $transport->shouldReceive('generate')->once()->ordered()->andReturn(Content::parse('Done', Role::MODEL));
        $context = $this->context();
        $this->assertSame('Done', $this->agent($tool, $transport)->reply('Internal test', [], $context));
        $this->assertSame(1, $tool->executions);
        $this->assertSame($context->user, $tool->seenUser);
    }

    public function test_fallback_reuses_previous_mutation_result_with_new_provider_id(): void
    {
        $tool = new ContextProbeTool;
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->ordered()->andReturn($this->toolCall());
        $transport->shouldReceive('generate')->once()->ordered()->andThrow(new RuntimeException('Transport failure'));
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system, $contents) {
            $this->assertSame(GeminiTransport::MODELS[1], $model);
            $this->assertCount(1, $contents);

            return true;
        })->andReturn($this->toolCall(id: 'fallback-id'));
        $transport->shouldReceive('generate')->once()->ordered()->withArgs(function ($model, $system, $contents) {
            $this->assertSame(1, end($contents)->parts[0]->functionResponse->response['execution']);

            return true;
        })->andReturn(Content::parse('Done', Role::MODEL));
        $this->assertSame('Done', $this->agent($tool, $transport)->reply('Internal test', [], $this->context()));
        $this->assertSame(1, $tool->executions);
    }

    public function test_fallback_cannot_introduce_a_new_write_after_any_write_attempt(): void
    {
        $tool = new ContextProbeTool;
        $transport = Mockery::mock(GeminiTransport::class);
        $transport->shouldReceive('generate')->once()->ordered()->andReturn($this->toolCall());
        $transport->shouldReceive('generate')->once()->ordered()->andThrow(new RuntimeException('Transport failure'));
        $transport->shouldReceive('generate')->once()->ordered()->andReturn($this->toolCall(['product_id' => 2], 'different'));
        try {
            $this->agent($tool, $transport)->reply('Internal test', [], $this->context());
            $this->fail('Fallback write accepted.');
        } catch (RuntimeException) {
            $this->assertSame(1, $tool->executions);
        }
    }

    public function test_transport_reservation_blocks_concurrent_and_uncertain_replays(): void
    {
        $store = new ChatExecutionStore;
        $context = $this->context();
        $id = (string) Str::uuid();
        $runs = 0;
        try {
            $store->run($context->user, $id, ['message' => 'Test'], function () use ($store, $context, $id, &$runs) {
                $runs++;
                $store->run($context->user, $id, ['message' => 'Test'], function () use (&$runs) {
                    $runs++;

                    return [];
                });

                return [];
            });
            $this->fail('Concurrent request executed.');
        } catch (ConflictHttpException) {
            $this->assertSame(1, $runs);
        }
        try {
            $store->run($context->user, $id, ['message' => 'Test'], function () use (&$runs) {
                $runs++;

                return [];
            });
            $this->fail('Uncertain request replayed.');
        } catch (ConflictHttpException) {
            $this->assertSame(1, $runs);
        }
    }

    public function test_fingerprint_normalizes_object_key_order_but_preserves_list_order(): void
    {
        $guard = $this->context()->mutations;
        $runs = 0;
        $op = function () use (&$runs) {
            return ['run' => ++$runs];
        };
        $first = $guard->execute('test', ['product_id' => 1, 'quantity' => 2], '1', $op);
        $this->assertSame($first, $guard->execute('test', ['quantity' => 2, 'product_id' => 1], '2', $op));
        $this->assertSame(1, $runs);
    }
}
