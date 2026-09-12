<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Commande;
use App\Models\Favorite;
use App\Models\Produit;
use App\Models\User;
use App\Services\FavoriteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Each test gets a new in-memory connection. Never reset a persisted database.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    private function user(string $role = 'client'): User
    {
        return User::create(['nom' => 'Favorites customer', 'email' => uniqid().'@example.test', 'mot_de_passe' => bcrypt('password'), 'role' => $role]);
    }

    private function product(): Produit
    {
        $category = Categorie::create(['nom' => uniqid('Category')]);

        return Produit::create(['nom' => uniqid('Favorite product '), 'prix' => 99.99, 'stock' => 10, 'categorie_id' => $category->id]);
    }

    public function test_all_endpoints_require_authentication(): void
    {
        $this->getJson('/api/favorites')->assertUnauthorized();
        $this->postJson('/api/favorites', ['produit_id' => 1])->assertUnauthorized();
        $this->deleteJson('/api/favorites/1')->assertUnauthorized();
    }

    public function test_add_is_idempotent_and_list_uses_own_favorites(): void
    {
        $a = $this->user();
        $b = $this->user();
        $product = $this->product();
        Sanctum::actingAs($a);
        $this->getJson('/api/favorites')->assertOk()->assertExactJson(['items' => []]);
        $this->postJson('/api/favorites', ['produit_id' => $product->id])->assertOk()->assertJsonPath('items.0.id', $product->id);
        $this->postJson('/api/favorites', ['produit_id' => $product->id])->assertOk()->assertJsonCount(1, 'items');
        $this->assertDatabaseCount('favorites', 1);
        $this->assertDatabaseHas('favorites', ['user_id' => $a->id, 'produit_id' => $product->id]);
        Sanctum::actingAs($b);
        $this->getJson('/api/favorites')->assertOk()->assertExactJson(['items' => []]);
        Sanctum::actingAs($a);
        $this->getJson('/api/favorites')->assertOk()->assertJsonPath('items.0.id', $product->id);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_account_switching_and_removal_are_isolated(): void
    {
        $a = $this->user();
        $b = $this->user();
        $productA = $this->product();
        $productB = $this->product();
        Sanctum::actingAs($a);
        $this->postJson('/api/favorites', ['produit_id' => $productA->id])->assertOk();
        Sanctum::actingAs($b);
        $this->getJson('/api/favorites')->assertExactJson(['items' => []]);
        $this->deleteJson('/api/favorites/'.$productA->id)->assertOk();
        $this->postJson('/api/favorites', ['produit_id' => $productB->id])->assertOk()->assertJsonCount(1, 'items');
        Sanctum::actingAs($a);
        $this->getJson('/api/favorites')->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $productA->id);
        $this->deleteJson('/api/favorites/'.$productA->id)->assertExactJson(['items' => []]);
        $this->deleteJson('/api/favorites/'.$productA->id)->assertExactJson(['items' => []]);
        Sanctum::actingAs($b);
        $this->getJson('/api/favorites')->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $productB->id);
    }

    public function test_client_cannot_select_owner_or_supply_product_snapshots(): void
    {
        $a = $this->user();
        Sanctum::actingAs($this->user());
        $product = $this->product();
        $this->getJson('/api/favorites?user_id='.$a->id)->assertUnprocessable();
        $this->postJson('/api/favorites', ['produit_id' => $product->id, 'user_id' => $a->id])->assertUnprocessable();
        $this->postJson('/api/favorites', ['produit_id' => $product->id, 'prix' => 1])->assertUnprocessable();
        $this->deleteJson('/api/favorites/'.$product->id, ['user_id' => $a->id])->assertUnprocessable();
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_invalid_and_nonexistent_products_are_handled(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        foreach ([null, 0, -1, 1.5, 'invalid', []] as $id) {
            $this->postJson('/api/favorites', ['produit_id' => $id])->assertUnprocessable();
        }
        $this->postJson('/api/favorites', ['produit_id' => 999999])->assertNotFound();
        $this->deleteJson('/api/favorites/999999')->assertOk()->assertExactJson(['items' => []]);
        $this->assertDatabaseCount('favorites', 0);
        // Existence is also enforced in the shared service, not just at the HTTP layer.
        $this->expectException(ModelNotFoundException::class);
        app(FavoriteService::class)->add($user, 999999);
    }

    public function test_product_data_is_current_and_relationships_are_connected(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        $product = $this->product();
        $this->postJson('/api/favorites', ['produit_id' => $product->id])->assertOk();
        $product->update(['nom' => 'Updated product', 'prix' => 12.34, 'image' => '/updated.png', 'stock' => 0, 'processor' => 'Updated CPU']);
        $product->categorie->update(['nom' => 'Updated category']);
        $this->getJson('/api/favorites')->assertOk()->assertJsonPath('items.0.nom', 'Updated product')
            ->assertJsonPath('items.0.prix', 12.34)->assertJsonPath('items.0.image', '/updated.png')
            ->assertJsonPath('items.0.stock', 0)->assertJsonPath('items.0.processor', 'Updated CPU')
            ->assertJsonPath('items.0.categorie.nom', 'Updated category');
        $favorite = Favorite::firstOrFail();
        $this->assertTrue($favorite->user->is($user));
        $this->assertTrue($favorite->produit->is($product));
        $this->assertSame($favorite->id, $user->favorites()->sole()->id);
        $this->assertSame($favorite->id, $product->favorites()->sole()->id);
    }

    public function test_deletable_product_cascades_favorites_for_all_users(): void
    {
        $a = $this->user();
        $b = $this->user();
        $product = $this->product();
        app(FavoriteService::class)->add($a, $product->id);
        app(FavoriteService::class)->add($b, $product->id);
        Sanctum::actingAs($this->user('admin'));
        $this->deleteJson('/api/products/'.$product->id)->assertOk();
        $this->assertDatabaseMissing('produits', ['id' => $product->id]);
        $this->assertDatabaseCount('favorites', 0);
        Sanctum::actingAs($a);
        $this->getJson('/api/favorites')->assertExactJson(['items' => []]);
    }

    public function test_product_referenced_by_order_remains_protected(): void
    {
        $user = $this->user();
        $product = $this->product();
        app(FavoriteService::class)->add($user, $product->id);
        $order = Commande::create(['user_id' => $user->id, 'statut' => 'en_cours', 'total' => 99.99]);
        $order->details()->create(['produit_id' => $product->id, 'quantite' => 1, 'prix_unitaire' => 99.99]);
        Sanctum::actingAs($this->user('admin'));
        $this->deleteJson('/api/products/'.$product->id)->assertUnprocessable();
        $this->assertDatabaseHas('produits', ['id' => $product->id]);
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'produit_id' => $product->id]);
        $this->assertDatabaseCount('details_commandes', 1);
    }

    public function test_user_deletion_cascades_only_their_favorites(): void
    {
        $a = $this->user();
        $b = $this->user();
        $product = $this->product();
        app(FavoriteService::class)->add($a, $product->id);
        app(FavoriteService::class)->add($b, $product->id);
        $a->delete();
        $this->assertDatabaseCount('favorites', 1);
        $this->assertDatabaseHas('favorites', ['user_id' => $b->id, 'produit_id' => $product->id]);
        $this->assertDatabaseHas('produits', ['id' => $product->id]);
    }
}
