<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Produit;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create(['nom' => 'Cart customer', 'email' => uniqid().'@example.test', 'mot_de_passe' => bcrypt('password'), 'role' => 'client']);
    }

    private function product(int $stock = 10): Produit
    {
        $category = Categorie::create(['nom' => uniqid('Category')]);

        return Produit::create(['nom' => uniqid('Cart product '), 'prix' => 1599.99, 'stock' => $stock, 'categorie_id' => $category->id]);
    }

    private function add(Produit $product, int $quantity = 1): array
    {
        return $this->postJson('/api/cart/items', ['produit_id' => $product->id, 'quantity' => $quantity])->assertOk()->json('items.0');
    }

    private function order(array $rows): array
    {
        return [
            'items' => array_map(fn ($row) => ['produit_id' => $row['id'], 'quantite' => $row['quantite'], 'cart_item_id' => $row['cart_item_id'], 'cart_updated_at' => $row['cart_updated_at']], $rows),
            'payment_method' => 'cash_on_delivery', 'delivery_address' => '123 Cart Test Street', 'delivery_phone' => '+212600000000',
        ];
    }

    public function test_all_cart_routes_require_authentication(): void
    {
        $this->getJson('/api/cart')->assertUnauthorized();
        $this->postJson('/api/cart/items', [])->assertUnauthorized();
        $this->patchJson('/api/cart/items/1', ['quantity' => 1])->assertUnauthorized();
        $this->deleteJson('/api/cart/items/1')->assertUnauthorized();
    }

    public function test_read_is_isolated_and_client_cannot_select_an_owner(): void
    {
        $a = $this->user();
        $b = $this->user();
        $product = $this->product();
        Sanctum::actingAs($a);
        $this->add($product, 2);
        Sanctum::actingAs($b);
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('items', []);
        $this->getJson('/api/cart?user_id='.$a->id)->assertUnprocessable();
        $this->postJson('/api/cart/items', ['user_id' => $a->id, 'produit_id' => $product->id, 'quantity' => 1])->assertUnprocessable();
        $this->assertDatabaseHas('cart_items', ['user_id' => $a->id, 'quantity' => 2]);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_add_increments_own_item_without_reserving_stock(): void
    {
        $product = $this->product();
        Sanctum::actingAs($this->user());
        $this->add($product, 2);
        $this->assertSame(3, $this->add($product)['quantite']);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_quantity_validation_and_stock_limits_apply_to_add_and_update(): void
    {
        $product = $this->product(3);
        Sanctum::actingAs($this->user());
        foreach ([0, -1, 1.5, '2', true, null, 101] as $quantity) {
            $this->postJson('/api/cart/items', ['produit_id' => $product->id, 'quantity' => $quantity])->assertUnprocessable();
            $this->patchJson('/api/cart/items/'.$product->id, ['quantity' => $quantity])->assertUnprocessable();
        }
        $this->add($product, 2);
        $this->postJson('/api/cart/items', ['produit_id' => $product->id, 'quantity' => 2])->assertUnprocessable();
        $this->patchJson('/api/cart/items/'.$product->id, ['quantity' => 4])->assertUnprocessable();
        $product->update(['stock' => 0]);
        $this->patchJson('/api/cart/items/'.$product->id, ['quantity' => 1])->assertUnprocessable();
        $this->assertDatabaseHas('cart_items', ['quantity' => 2]);
    }

    public function test_unknown_product_and_missing_item_are_safe(): void
    {
        Sanctum::actingAs($this->user());
        $product = $this->product();
        $missing = $product->id + 1000;
        $this->postJson('/api/cart/items', ['produit_id' => $missing, 'quantity' => 1])->assertNotFound();
        $this->patchJson('/api/cart/items/'.$missing, ['quantity' => 1])->assertNotFound();
        $this->patchJson('/api/cart/items/'.$product->id, ['quantity' => 1])->assertNotFound();
        $this->deleteJson('/api/cart/items/'.$missing)->assertOk()->assertJsonPath('items', []);
    }

    public function test_current_database_prices_are_returned_and_price_payload_is_rejected(): void
    {
        Sanctum::actingAs($this->user());
        $product = $this->product();
        $this->add($product, 2);
        $product->update(['prix' => 12.34]);
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('items.0.prix', '12.34')->assertJsonPath('total', '24.68');
        $this->postJson('/api/cart/items', ['produit_id' => $product->id, 'quantity' => 1, 'prix' => 0])->assertUnprocessable();
        $this->assertDatabaseHas('cart_items', ['quantity' => 2]);
    }

    public function test_update_and_remove_never_affect_another_users_item(): void
    {
        $product = $this->product();
        $a = $this->user();
        $b = $this->user();
        Sanctum::actingAs($a);
        $this->add($product, 2);
        Sanctum::actingAs($b);
        $this->patchJson('/api/cart/items/'.$product->id, ['quantity' => 3])->assertNotFound();
        $this->deleteJson('/api/cart/items/'.$product->id)->assertOk();
        $this->add($product);
        $this->patchJson('/api/cart/items/'.$product->id, ['quantity' => 3])->assertOk()->assertJsonPath('items.0.quantite', 3);
        $this->deleteJson('/api/cart/items/'.$product->id)->assertOk()->assertJsonPath('items', []);
        $this->assertDatabaseHas('cart_items', ['user_id' => $a->id, 'quantity' => 2]);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_successful_checkout_clears_only_purchased_unchanged_items(): void
    {
        Sanctum::actingAs($this->user());
        $product = $this->product();
        $snapshot = $this->add($product, 2);
        $other = $this->product();
        $this->add($other);
        $product->update(['prix' => 20]);
        $this->postJson('/api/orders', $this->order([$snapshot]))->assertCreated()->assertJsonPath('total', 40);
        $this->getJson('/api/cart')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $other->id);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(10, $other->fresh()->stock);
    }

    public function test_failed_checkout_preserves_cart_and_order_state(): void
    {
        Sanctum::actingAs($this->user());
        $product = $this->product();
        $snapshot = $this->add($product, 2);
        $product->update(['stock' => 1]);
        $this->postJson('/api/orders', $this->order([$snapshot]))->assertUnprocessable();
        $this->assertDatabaseCount('commandes', 0);
        $this->assertDatabaseHas('cart_items', ['quantity' => 2]);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_newer_quantity_edits_survive_checkout_even_when_changed_back(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-11 10:00:00.000000'));
        try {
            Sanctum::actingAs($this->user());
            $product = $this->product();
            $snapshot = $this->add($product, 2);
            $this->patchJson('/api/cart/items/'.$product->id, ['quantity' => 3])->assertOk();
            $this->patchJson('/api/cart/items/'.$product->id, ['quantity' => 2])->assertOk();
            $this->postJson('/api/orders', $this->order([$snapshot]))->assertCreated();
            $this->assertDatabaseHas('cart_items', ['quantity' => 2]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_deleted_and_readded_row_survives_old_checkout_snapshot(): void
    {
        Sanctum::actingAs($this->user());
        $product = $this->product();
        $snapshot = $this->add($product);
        $this->deleteJson('/api/cart/items/'.$product->id)->assertOk();
        $this->add($product);
        $this->postJson('/api/orders', $this->order([$snapshot]))->assertCreated();
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_forged_cross_user_cleanup_and_legacy_checkout_do_not_clear_other_cart(): void
    {
        $a = $this->user();
        $b = $this->user();
        $product = $this->product();
        Sanctum::actingAs($a);
        $snapshot = $this->add($product);
        Sanctum::actingAs($b);
        $this->add($product);
        $this->postJson('/api/orders', $this->order([$snapshot]))->assertCreated();
        $this->assertDatabaseCount('cart_items', 2);
        $legacy = $this->order([$snapshot]);
        unset($legacy['items'][0]['cart_item_id'], $legacy['items'][0]['cart_updated_at']);
        $this->postJson('/api/orders', $legacy)->assertCreated();
        $this->assertDatabaseCount('cart_items', 2);
    }

    public function test_cleanup_failure_rolls_back_order_stock_and_cart(): void
    {
        Sanctum::actingAs($this->user());
        $product = $this->product();
        $snapshot = $this->add($product);
        $this->partialMock(CartService::class, function ($mock) {
            $mock->shouldReceive('clearPurchased')->once()->andThrow(new \RuntimeException('Simulated transaction failure'));
        });
        $this->postJson('/api/orders', $this->order([$snapshot]))->assertStatus(500);
        $this->assertDatabaseCount('commandes', 0);
        $this->assertDatabaseCount('details_commandes', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(10, $product->fresh()->stock);
    }
}
