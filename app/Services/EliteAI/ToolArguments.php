<?php

namespace App\Services\EliteAI;

use Gemini\Data\Schema;
use Gemini\Enums\DataType;
use Illuminate\Validation\ValidationException;

/** Strict JSON types and allowlisted keys, checked before any database access. */
class ToolArguments
{
    public static function only(array $arguments, array $keys): void
    {
        if (array_diff(array_keys($arguments), $keys)) {
            throw ValidationException::withMessages(['arguments' => 'Unknown argument.']);
        }
    }

    public static function integer(mixed $value, string $field, int $min = 1, int $max = PHP_INT_MAX): int
    {
        if (! is_int($value) || $value < $min || $value > $max) {
            throw ValidationException::withMessages([$field => 'Expected an integer within the declared range.']);
        }

        return $value;
    }

    public static function productId(array $arguments): int
    {
        self::only($arguments, ['product_id']);

        return self::integer($arguments['product_id'] ?? null, 'product_id');
    }

    public static function productIds(array $arguments, int $maximum = 4): array
    {
        self::only($arguments, ['product_ids']);
        $ids = $arguments['product_ids'] ?? null;
        if (! is_array($ids) || ! array_is_list($ids) || count($ids) < 2 || count($ids) > $maximum) {
            throw ValidationException::withMessages(['product_ids' => 'Provide the declared number of distinct product IDs.']);
        }
        foreach ($ids as $id) {
            self::integer($id, 'product_ids');
        }
        if (count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['product_ids' => 'Product IDs must be distinct.']);
        }

        return $ids;
    }

    public static function productIdSchema(): Schema
    {
        return new Schema(type: DataType::OBJECT, properties: [
            'product_id' => new Schema(type: DataType::INTEGER, description: 'Positive product ID obtained from catalog tools.', minimum: 1),
        ], required: ['product_id']);
    }

    public static function productIdsSchema(int $maximum = 4): Schema
    {
        return new Schema(type: DataType::OBJECT, properties: [
            'product_ids' => new Schema(type: DataType::ARRAY, description: 'Distinct existing product IDs; discover IDs with search_products first.', minItems: '2', maxItems: (string) $maximum, items: new Schema(type: DataType::INTEGER, minimum: 1)),
        ], required: ['product_ids']);
    }
}
