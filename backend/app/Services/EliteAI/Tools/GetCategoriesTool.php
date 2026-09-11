<?php

namespace App\Services\EliteAI\Tools;

use App\Models\Categorie;
use App\Services\EliteAI\Contracts\AgentTool;
use App\Services\EliteAI\ToolArguments;
use Gemini\Data\Schema;
use Gemini\Enums\DataType;

class GetCategoriesTool implements AgentTool
{
    public function name(): string
    {
        return 'get_categories';
    }

    public function description(): string
    {
        return 'Read real catalog category IDs, names and accurate product counts. All arguments optional. At most 50 per page, in ID order; use next_after_id if has_more is true. No slug field exists.';
    }

    public function schema(): Schema
    {
        return new Schema(type: DataType::OBJECT, properties: [
            'limit' => new Schema(type: DataType::INTEGER, description: 'Maximum categories, default 50.', minimum: 1, maximum: 50),
            'after_id' => new Schema(type: DataType::INTEGER, description: 'Optional positive category ID cursor from next_after_id.', minimum: 1),
        ]);
    }

    public function execute(array $arguments): array
    {
        ToolArguments::only($arguments, ['limit', 'after_id']);
        $limit = array_key_exists('limit', $arguments) ? ToolArguments::integer($arguments['limit'], 'limit', 1, 50) : 50;
        $after = array_key_exists('after_id', $arguments) ? ToolArguments::integer($arguments['after_id'], 'after_id') : 0;
        $rows = Categorie::query()->select(['id', 'nom'])->withCount('produits')
            ->where('id', '>', $after)->orderBy('id')->limit($limit + 1)->get();
        $categories = $rows->take($limit)->map(fn ($category) => [
            'id' => $category->id, 'name' => $category->nom, 'product_count' => $category->produits_count,
        ])->values()->all();
        $hasMore = $rows->count() > $limit;

        return ['status' => 'ok', 'categories' => $categories, 'returned_count' => count($categories), 'has_more' => $hasMore, 'next_after_id' => $hasMore ? $categories[count($categories) - 1]['id'] : null];
    }
}
