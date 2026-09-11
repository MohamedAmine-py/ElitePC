<?php

namespace App\Services\EliteAI\Tools;

use App\Services\EliteAI\Contracts\AgentTool;
use App\Services\EliteAI\ProductFacts;
use App\Services\EliteAI\ToolArguments;
use Gemini\Data\Schema;

class GetProductDetailsTool implements AgentTool
{
    public function name(): string
    {
        return 'get_product_details';
    }

    public function description(): string
    {
        return 'REQUIRED for requests such as tell me everything about a product: search_products discovers its ID, then call this tool before answering. Read one specific product by real catalog ID: current USD price, stock, fuller description, image, category and stored specs. Missing specs remain unknown. Never guess IDs.';
    }

    public function schema(): Schema
    {
        return ToolArguments::productIdSchema();
    }

    public function execute(array $arguments): array
    {
        $id = ToolArguments::productId($arguments);
        $product = ProductFacts::query()->find($id);

        return $product
            ? ['status' => 'ok', 'currency' => 'USD', 'product' => ProductFacts::from($product)]
            : ['status' => 'not_found', 'product_id' => $id];
    }
}
