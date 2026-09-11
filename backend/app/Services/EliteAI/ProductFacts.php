<?php

namespace App\Services\EliteAI;

use App\Models\Produit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Public catalog projection shared by detail, comparison and compatibility tools. */
class ProductFacts
{
    public static function query(): Builder
    {
        return Produit::query()->select([
            'id', 'nom', 'prix', 'stock', 'categorie_id', 'description', 'image',
            'brand', 'processor', 'graphics_card', 'ram_details', 'storage_details', 'is_custom_build',
        ])->with('categorie:id,nom');
    }

    public static function from(Produit $product): array
    {
        $description = strip_tags($product->description ?? '');

        return [
            'id' => $product->id,
            'name' => $product->nom,
            'price' => number_format((float) $product->prix, 2, '.', ''),
            'stock' => $product->stock,
            'category' => $product->categorie?->nom,
            'description' => Str::limit($description, 4000),
            'description_truncated' => mb_strlen($description) > 4000,
            'image' => $product->image,
            'specifications' => $product->only(['brand', 'processor', 'graphics_card', 'ram_details', 'storage_details', 'is_custom_build']),
        ];
    }

    /** Called only with the validated, bounded ID lists from ToolArguments. */
    public static function many(array $ids): array
    {
        $rows = self::query()->whereIn('id', $ids)->get()->keyBy('id');
        $missing = array_values(array_filter($ids, fn ($id) => ! $rows->has($id)));
        if ($missing) {
            return ['status' => 'not_found', 'missing_product_ids' => $missing, 'products' => []];
        }

        return [
            'status' => 'ok', 'currency' => 'USD',
            'products' => array_map(fn ($id) => self::from($rows->get($id)), $ids),
        ];
    }
}
