<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Produit;
use App\Services\GeminiRagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeminiRagServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_context_formats_stored_prices_as_us_dollars_without_conversion(): void
    {
        $category = Categorie::create(['nom' => 'Graphics Cards']);

        Produit::create([
            'nom' => 'GeForce RTX 4090 Founders Edition',
            'prix' => 1599.99,
            'stock' => 2,
            'categorie_id' => $category->id,
        ]);

        $context = app(GeminiRagService::class)->buildProductCatalogContext();

        $this->assertStringContainsString('Price: $1,599.99', $context);
        $this->assertStringNotContainsString(' EUR', $context);
        $this->assertStringNotContainsString(' DA', $context);
        $this->assertStringNotContainsString(' MAD', $context);
    }
}
