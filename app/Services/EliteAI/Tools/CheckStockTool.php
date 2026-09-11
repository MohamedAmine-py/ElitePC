<?php

namespace App\Services\EliteAI\Tools;

use App\Models\Produit;
use App\Services\EliteAI\Contracts\AgentTool;
use App\Services\EliteAI\ToolArguments;
use Gemini\Data\Schema;

class CheckStockTool implements AgentTool
{
    public function name(): string
    {
        return 'check_stock';
    }

    public function description(): string
    {
        return 'REQUIRED for how many units or exact availability of a specific product: discover the ID with search_products, then call this tool before answering. Read exact current stock quantity and in_stock boolean. No reservations or stock changes.';
    }

    public function schema(): Schema
    {
        return ToolArguments::productIdSchema();
    }

    public function execute(array $arguments): array
    {
        $id = ToolArguments::productId($arguments);
        $product = Produit::query()->select(['id', 'nom', 'stock'])->find($id);

        return $product
            ? ['status' => 'ok', 'product' => ['id' => $product->id, 'name' => $product->nom, 'stock' => $product->stock, 'in_stock' => $product->stock > 0]]
            : ['status' => 'not_found', 'product_id' => $id];
    }
}
