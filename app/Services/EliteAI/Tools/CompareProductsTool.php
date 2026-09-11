<?php

namespace App\Services\EliteAI\Tools;

use App\Services\EliteAI\Contracts\AgentTool;
use App\Services\EliteAI\ProductFacts;
use App\Services\EliteAI\ToolArguments;
use Gemini\Data\Schema;

class CompareProductsTool implements AgentTool
{
    public function name(): string
    {
        return 'compare_products';
    }

    public function description(): string
    {
        return 'Read factual comparable data for 2 to 4 distinct real catalog product IDs, preserving requested order. Returns exact USD prices, stock, descriptions and stored specs; no winner or invented benchmark. Discover IDs first. If any ID is missing, return not_found instead of a partial comparison.';
    }

    public function schema(): Schema
    {
        return ToolArguments::productIdsSchema();
    }

    public function execute(array $arguments): array
    {
        return ProductFacts::many(ToolArguments::productIds($arguments));
    }
}
