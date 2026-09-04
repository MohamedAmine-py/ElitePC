<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_item_invoice_download_uses_the_existing_authenticated_endpoint(): void
    {
        $commande = $this->orderWithItems([['Elite GPU', 1, 1899.99]]);
        $commande->update(['statut' => 'en_cours']);
        Sanctum::actingAs($commande->user);

        $response = $this->get("/api/orders/{$commande->id}/invoice");

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->writeSample('elite-pc-invoice-single-item.pdf', $commande);
    }

    public function test_multi_item_invoice_uses_line_values_and_authoritative_total_without_vat(): void
    {
        $commande = $this->orderWithItems([
            ['GeForce RTX 4090 Founders Edition', 1, 1599.99],
            ['Intel Core i9-14900K', 2, 589.50],
            ['Corsair Vengeance DDR5 32GB', 3, 129.25],
        ]);
        $html = view('invoices.invoice', ['commande' => $commande])->render();

        $this->assertStringContainsString('1,599.99 EUR', $html);
        $this->assertStringContainsString(number_format((float) $commande->total, 2).' EUR', $html);
        $this->assertStringNotContainsString('VAT', $html);
        $this->assertStringNotContainsString('Tax', $html);
        $this->writeSample('elite-pc-invoice-multi-item.pdf', $commande);
    }

    public function test_long_product_names_render_in_the_invoice(): void
    {
        $longName = 'Elite PC Professional Workstation with Extended Liquid Cooling, High Capacity Memory, and Multi-GPU Rendering Configuration';
        $commande = $this->orderWithItems([[$longName, 1, 6999.99]]);
        $html = view('invoices.invoice', ['commande' => $commande])->render();

        $this->assertStringContainsString($longName, $html);
        $this->writeSample('elite-pc-invoice-long-name.pdf', $commande);
    }

    public function test_large_order_spans_multiple_pages(): void
    {
        $items = [];

        for ($index = 1; $index <= 55; $index++) {
            $items[] = ["Hardware component {$index} with a descriptive product name", 1, 10 + $index];
        }

        $commande = $this->orderWithItems($items);
        $pdf = $this->renderPdf($commande);
        preg_match_all('/\/Type\s*\/Page\b/', $pdf, $pages);

        $this->assertGreaterThan(1, count($pages[0]));
        $this->writeSample('elite-pc-invoice-multi-page.pdf', $commande, $pdf);
    }

    private function orderWithItems(array $items): Commande
    {
        $user = User::create([
            'nom' => 'Alex Customer',
            'email' => 'alex.customer@example.com',
            'mot_de_passe' => bcrypt('password'),
            'role' => 'client',
        ]);
        $category = Categorie::create(['nom' => 'Invoice '.Str::uuid()]);
        $total = collect($items)->sum(fn (array $item) => $item[1] * $item[2]);
        $commande = Commande::create([
            'user_id' => $user->id,
            'statut' => 'validee',
            'total' => $total,
            'payment_method' => 'credit_card',
            'delivery_address' => '42 Hardware Avenue, Casablanca',
            'delivery_phone' => '+212 600 000 000',
            'estimated_delivery_date' => now()->addDays(4),
        ]);

        foreach ($items as [$name, $quantity, $unitPrice]) {
            $product = Produit::create([
                'nom' => $name,
                'prix' => $unitPrice,
                'stock' => 20,
                'categorie_id' => $category->id,
                'brand' => 'Elite PC',
            ]);
            $commande->details()->create([
                'produit_id' => $product->id,
                'quantite' => $quantity,
                'prix_unitaire' => $unitPrice,
            ]);
        }

        return $commande->load(['details.produit', 'user']);
    }

    private function renderPdf(Commande $commande): string
    {
        return Pdf::loadView('invoices.invoice', ['commande' => $commande])
            ->setPaper('a4')
            ->output();
    }

    private function writeSample(string $filename, Commande $commande, ?string $contents = null): void
    {
        if (! env('GENERATE_INVOICE_SAMPLES')) {
            return;
        }

        $directory = base_path('output/pdf');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($directory.DIRECTORY_SEPARATOR.$filename, $contents ?? $this->renderPdf($commande));
    }
}
