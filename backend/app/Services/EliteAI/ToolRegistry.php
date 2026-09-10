<?php

namespace App\Services\EliteAI;

use App\Services\EliteAI\Contracts\AgentTool;
use App\Services\EliteAI\Tools\SearchProductsTool;
use Gemini\Data\FunctionDeclaration;
use Gemini\Data\Tool;
use InvalidArgumentException;

class ToolRegistry
{
    /** @var array<string, AgentTool> Explicit server-owned allowlist. */
    private array $tools;

    public function __construct(SearchProductsTool $search)
    {
        $this->tools = [$search->name() => $search];
    }

    public function resolve(string $name): AgentTool
    {
        return $this->tools[$name] ?? throw new InvalidArgumentException('Unknown tool.');
    }

    public function definitions(): array
    {
        return [new Tool(functionDeclarations: array_map(
            fn (AgentTool $tool) => new FunctionDeclaration($tool->name(), $tool->description(), $tool->schema()),
            array_values($this->tools),
        ))];
    }
}
