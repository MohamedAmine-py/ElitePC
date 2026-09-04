<?php

namespace App\Services;

use App\Models\Produit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * GeminiRagService — Retrieval-Augmented Generation for the Elite PC AI chat.
 *
 * This service is responsible for:
 * 1. Fetching the live product catalog from the database (the "Retrieval" step).
 * 2. Building a context-enriched system prompt that grounds the AI's answers
 *    strictly in real inventory data (the "Augmentation" step).
 *
 * The controller handles the "Generation" step by sending the prompt to Gemini.
 */
class GeminiRagService
{
    /**
     * Fetch the full product catalog with categories and format it as
     * a structured text context block for injection into the AI prompt.
     *
     * Each product entry includes: name, price, stock status, brand,
     * processor, GPU, RAM, storage, custom-build flag, and category.
     *
     * @return string Formatted catalog text ready for prompt injection.
     */
    public function buildProductCatalogContext(): string
    {
        // Eager-load category to avoid N+1 queries
        $products = Produit::with('categorie')
            ->select([
                'id', 'nom', 'description', 'prix', 'stock',
                'brand', 'processor', 'graphics_card',
                'ram_details', 'storage_details', 'is_custom_build',
                'categorie_id',
            ])
            ->get();

        if ($products->isEmpty()) {
            Log::warning('GeminiRagService: Product catalog is empty — AI will have no product data.');

            return 'No products are currently available in the store catalog.';
        }

        // Build a human-readable, structured text block for each product
        $catalogLines = [];

        foreach ($products as $product) {
            $entry = "- **{$product->nom}**";
            $entry .= ' | Price: $'.number_format($product->prix, 2);
            $entry .= ' | Stock: '.($product->stock > 0 ? "{$product->stock} units available" : 'OUT OF STOCK');

            // Category name (if relationship loaded)
            if ($product->categorie) {
                $entry .= " | Category: {$product->categorie->nom}";
            }

            // Hardware specifications (only include non-null fields)
            $specs = [];
            if ($product->brand) {
                $specs[] = "Brand: {$product->brand}";
            }
            if ($product->processor) {
                $specs[] = "Processor: {$product->processor}";
            }
            if ($product->graphics_card) {
                $specs[] = "GPU: {$product->graphics_card}";
            }
            if ($product->ram_details) {
                $specs[] = "RAM: {$product->ram_details}";
            }
            if ($product->storage_details) {
                $specs[] = "Storage: {$product->storage_details}";
            }
            if ($product->is_custom_build) {
                $specs[] = 'Type: Custom Build';
            }

            if (! empty($specs)) {
                $entry .= ' | Specs: '.implode(', ', $specs);
            }

            // Short description (truncated to avoid prompt bloat)
            if ($product->description) {
                $entry .= ' | Description: '.Str::limit($product->description, 150);
            }

            $catalogLines[] = $entry;
        }

        return implode("\n", $catalogLines);
    }

    /**
     * Build the complete system instruction for the Gemini model.
     *
     * Combines the Elite PC assistant persona with the live product catalog,
     * wrapped in clear delimiters so the model can distinguish between
     * instructions and data.
     *
     * @param  string  $catalogContext  The formatted product catalog text.
     * @return string The full system prompt.
     */
    public function buildSystemPrompt(string $catalogContext): string
    {
        $persona = <<<'PERSONA'
You are 'Elite PC Assistant', an expert PC hardware shopping assistant for 'Elite PC', a premium storefront specializing in custom gamer PCs, high-end workstations, PC components, and gaming peripherals.

Your tone must be professional, highly technical, enthusiastic about PC gaming/hardware, and helpful. You are an expert in modern PC architectures (Intel Core 13th/14th Gen, AMD Ryzen 7000 series, NVIDIA RTX 40-series, AMD Radeon RX 7000 series, DDR5 RAM, NVMe Gen4/Gen5, etc.).

Provide assistance regarding:
- Custom PC builds and pre-built recommendations (Gamer PCs and Workstations)
- Hardware compatibility (e.g., 'Will this RTX 4090 fit in this NZXT case?', 'Is this DDR5 RAM compatible with this AM5 motherboard?')
- Product comparisons and hardware advice for gaming or professional workloads
- Peripheral advice (high polling rate mice, mechanical keyboards, gaming headsets)
- Current catalog availability, exact prices, stock, and product specifications

You do not have access to customer orders, shipment tracking, delivery estimates, returns, warranties, or private account data. Do not claim that you can perform those tasks.

**Important boundaries:** This store specializes strictly in high-end PC hardware and gaming. If a customer asks about unrelated items (like appliances, clothes, console exclusives, or general smartphones not related to PC gaming), politely inform them that 'Elite PC' focuses exclusively on the ultimate PC gaming and workstation experience.

Keep responses clear, well-formatted in Markdown (use bullet points or bold text for hardware names), and concise. Never display raw JSON or code. Be precise, technical, and reassuring.
PERSONA;

        $groundingInstruction = <<<'GROUNDING'

=== CRITICAL DATA-GROUNDING RULES ===

1. The <PRODUCT_CATALOG> section below contains the REAL, LIVE inventory of Elite PC — fetched directly from the store database at the time of this conversation.
2. When a customer asks about products, availability, pricing, or specifications, you MUST answer ONLY based on the data in <PRODUCT_CATALOG>. Do NOT invent products, prices, or specifications.
3. If a customer asks about a product that is NOT in the catalog, respond honestly: "That specific product is not currently available in our Elite PC catalog. Here are similar options we do carry: ..."
4. If a product shows "OUT OF STOCK", inform the customer and suggest alternatives from the catalog that ARE in stock.
5. Always quote the exact price and stock count from the catalog. All catalog prices are in USD ($). Never estimate, convert, or round prices.
6. If a product lacks hardware specs (processor, GPU, etc.), it is likely a peripheral or accessory — do not guess specs.
GROUNDING;

        // Assemble the final system prompt with clear data delimiters
        return $persona
            .$groundingInstruction
            ."\n\n<PRODUCT_CATALOG>\n"
            .$catalogContext
            ."\n</PRODUCT_CATALOG>";
    }
}
