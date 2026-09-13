<?php

namespace App\Services\EliteAI\Tools;

use App\Models\Produit;
use App\Services\EliteAI\Contracts\AgentTool;
use Gemini\Data\Schema;
use Gemini\Enums\DataType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SearchProductsTool implements AgentTool
{
    public const MAX_RESULTS = 10;

    public function name(): string
    {
        return 'search_products';
    }

    public function description(): string
    {
        return 'Read current ElitePC products, exact USD prices, stock and known specs. ALL filters are optional; no keyword or category is required. Use in_stock=true for broad availability questions, or no arguments to browse. Filters combine with AND. Normally returns at most 10 products, cheapest first; has_more indicates a partial result. For explicit all/every/entire catalog requests, set all=true to return every matching live product without a row limit. Omit filters the customer did not request. No mutations.';
    }

    public function schema(): Schema
    {
        return new Schema(type: DataType::OBJECT, properties: [
            'search' => new Schema(type: DataType::STRING, description: 'Literal keyword in name, brand, description or hardware specs; max 100 characters.'),
            'category' => new Schema(type: DataType::STRING, description: 'Exact category name, case-insensitive; max 100 characters.'),
            'min_price' => new Schema(type: DataType::NUMBER, description: 'Minimum inclusive price in USD, zero or greater.'),
            'max_price' => new Schema(type: DataType::NUMBER, description: 'Maximum inclusive price in USD, zero or greater.'),
            'in_stock' => new Schema(type: DataType::BOOLEAN, description: 'True: stock > 0; false: stock = 0; omit for either.'),
            'limit' => new Schema(type: DataType::INTEGER, description: 'Number of products, 1 to 10; default 10. Ignored when all=true.'),
            'all' => new Schema(type: DataType::BOOLEAN, description: 'Default false. Set true ONLY when the customer explicitly requests all/every matching product or the entire catalog; ignores limit while retaining requested filters.'),
        ]);
    }

    public function execute(array $arguments): array
    {
        $allowed = ['search', 'category', 'min_price', 'max_price', 'in_stock', 'limit', 'all'];
        if (array_diff(array_keys($arguments), $allowed)) {
            throw ValidationException::withMessages(['arguments' => 'Unknown search filter.']);
        }
        // JSON types are enforced separately: Laravel numeric/boolean rules also accept strings.
        foreach ($arguments as $key => $value) {
            $valid = match ($key) {
                'search', 'category' => is_string($value),
                'min_price', 'max_price' => (is_int($value) || is_float($value)) && is_finite((float) $value),
                'in_stock', 'all' => is_bool($value),
                'limit' => is_int($value),
            };
            if (! $valid) {
                throw ValidationException::withMessages([$key => 'Invalid filter type.']);
            }
        }
        $filters = Validator::make($arguments, [
            'search' => 'sometimes|string|max:100', 'category' => 'sometimes|string|max:100',
            'min_price' => 'sometimes|numeric|min:0|max:99999999.99',
            'max_price' => 'sometimes|numeric|min:0|max:99999999.99',
            'in_stock' => 'sometimes|boolean', 'all' => 'sometimes|boolean', 'limit' => 'sometimes|integer|min:1|max:'.self::MAX_RESULTS,
        ])->validate();
        if (isset($filters['min_price'], $filters['max_price']) && $filters['min_price'] > $filters['max_price']) {
            throw ValidationException::withMessages(['max_price' => 'Maximum must be at least minimum.']);
        }
        $query = Produit::query()->with('categorie:id,nom')->select([
            'id', 'nom', 'prix', 'stock', 'categorie_id', 'description', 'brand',
            'processor', 'graphics_card', 'ram_details', 'storage_details', 'is_custom_build',
        ]);
        if (! empty(trim($filters['search'] ?? ''))) {
            // Wildcards are literal, with a portable explicit escape character.
            $keyword = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($filters['search']))).'%';
            $query->where(function ($query) use ($keyword) {
                foreach (['nom', 'brand', 'description', 'processor', 'graphics_card', 'ram_details', 'storage_details'] as $column) {
                    $query->orWhereRaw("LOWER($column) LIKE ? ESCAPE '!'", [$keyword]);
                }
            });
        }
        if (isset($filters['category'])) {
            $query->whereHas('categorie', fn ($q) => $q->whereRaw('LOWER(nom) = ?', [mb_strtolower(trim($filters['category']))]));
        }
        foreach (['min_price' => '>=', 'max_price' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $query->where('prix', $operator, $filters[$key]);
            }
        }
        if (array_key_exists('in_stock', $filters)) {
            $query->where('stock', $filters['in_stock'] ? '>' : '=', 0);
        }
        $all = $filters['all'] ?? false;
        $limit = $filters['limit'] ?? self::MAX_RESULTS;
        $rows = $query->orderBy('prix')->orderBy('id')->when(! $all, fn ($query) => $query->limit($limit + 1))->get();
        $products = ($all ? $rows : $rows->take($limit))->map(fn (Produit $product) => [
            'id' => $product->id, 'name' => $product->nom,
            'price' => number_format((float) $product->prix, 2, '.', ''), 'stock' => $product->stock,
            'category' => $product->categorie?->nom,
            'description' => Str::limit(strip_tags($product->description ?? ''), 500),
            'specifications' => $product->only(['brand', 'processor', 'graphics_card', 'ram_details', 'storage_details', 'is_custom_build']),
        ])->values()->all();

        return ['currency' => 'USD', 'products' => $products, 'returned_count' => count($products), 'has_more' => ! $all && $rows->count() > $limit];
    }
}
