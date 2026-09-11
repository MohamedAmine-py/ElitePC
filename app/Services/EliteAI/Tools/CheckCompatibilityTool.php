<?php

namespace App\Services\EliteAI\Tools;

use App\Services\EliteAI\Contracts\AgentTool;
use App\Services\EliteAI\ProductFacts;
use App\Services\EliteAI\ToolArguments;
use Gemini\Data\Schema;

class CheckCompatibilityTool implements AgentTool
{
    public function name(): string
    {
        return 'check_compatibility';
    }

    public function description(): string
    {
        return 'Evaluate whether current catalog evidence can confirm compatibility for exactly 2 distinct products. Current schema lacks verified paired compatibility attributes, so existing pairs return insufficient_data with known facts, NOT compatible or incompatible. Never override this with model knowledge or infer compatibility from model names.';
    }

    public function schema(): Schema
    {
        return ToolArguments::productIdsSchema(2);
    }

    public function execute(array $arguments): array
    {
        $result = ProductFacts::many(ToolArguments::productIds($arguments, 2));
        if ($result['status'] !== 'ok') {
            return $result;
        }

        // No socket/RAM-support/clearance/power-requirement relationships exist in
        // the schema. A DDR5 or AM5 mention alone is not evidence for a paired check.
        // Add a deterministic rule only when both sides have verified input data.
        return [
            'status' => 'insufficient_data',
            'reason' => 'The catalog does not contain enough verified paired specifications to confirm compatibility. Nullable descriptive specifications and product names alone are not sufficient.',
            'known_facts' => array_map(fn ($product) => array_intersect_key($product, array_flip([
                'id', 'name', 'category', 'description', 'description_truncated', 'specifications',
            ])), $result['products']),
        ];
    }
}
