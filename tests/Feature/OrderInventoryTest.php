<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_an_order(): void
    {
        $user = $this->user();
        $produit = $this->product(stock: 5, price: 12.50);
        Sanctum::actingAs($user);

        $this->postJson('/api/orders', $this->orderPayload([
            ['produit_id' => $produit->id, 'quantite' => 2],
        ]))->assertCreated();

        $this->assertDatabaseHas('commandes', [
            'user_id' => $user->id,
            'statut' => 'en_cours',
            'total' => 25,
        ]);
        $this->assertDatabaseHas('details_commandes', [
            'produit_id' => $produit->id,
            'quantite' => 2,
            'prix_unitaire' => 12.50,
        ]);
        $this->assertSame(3, $produit->fresh()->stock);
    }

    public function test_checkout_uses_database_prices_instead_of_client_prices(): void
    {
        $user = $this->user();
        $produit = $this->product(stock: 5, price: 12.50);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', $this->orderPayload([
            ['produit_id' => $produit->id, 'quantite' => 2, 'prix' => 0.01],
        ]));

        $response->assertCreated()
            ->assertJsonPath('total', 25)
            ->assertJsonPath('details.0.prix_unitaire', 12.5);
    }

    public function test_insufficient_aggregated_stock_does_not_create_an_order_or_change_stock(): void
    {
        $user = $this->user();
        $produit = $this->product(stock: 3);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', $this->orderPayload([
            ['produit_id' => $produit->id, 'quantite' => 2],
            ['produit_id' => $produit->id, 'quantite' => 2],
        ]));

        $response->assertUnprocessable();
        $this->assertDatabaseCount('commandes', 0);
        $this->assertSame(3, $produit->fresh()->stock);
    }

    public function test_cancelling_an_order_restores_stock_exactly_once(): void
    {
        [$admin, $commande, $produit] = $this->orderWithReservedStock();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/orders/{$commande->id}/status", ['statut' => 'annulee'])
            ->assertOk();
        $this->assertSame(5, $produit->fresh()->stock);

        $this->patchJson("/api/orders/{$commande->id}/status", ['statut' => 'annulee'])
            ->assertOk();
        $this->assertSame(5, $produit->fresh()->stock);

    }

    public function test_deleting_an_active_order_restores_stock_safely(): void
    {
        [$admin, $commande, $produit] = $this->orderWithReservedStock();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/orders/{$commande->id}")->assertOk();
        $this->assertSame(5, $produit->fresh()->stock);

        $this->assertDatabaseMissing('commandes', ['id' => $commande->id]);
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        [, $commande] = $this->orderWithReservedStock();
        Sanctum::actingAs($this->user());

        $this->getJson("/api/orders/{$commande->id}")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_an_order(): void
    {
        $produit = $this->product();

        $this->postJson('/api/orders', $this->orderPayload([
            ['produit_id' => $produit->id, 'quantite' => 1],
        ]))->assertUnauthorized();

        $this->assertDatabaseCount('commandes', 0);
        $this->assertSame(5, $produit->fresh()->stock);
    }

    private function user(string $role = 'client'): User
    {
        return User::create([
            'nom' => ucfirst($role),
            'email' => $role.'-'.uniqid().'@example.com',
            'mot_de_passe' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    private function product(int $stock = 5, float $price = 10): Produit
    {
        $categorie = Categorie::create(['nom' => 'Test '.uniqid()]);

        return Produit::create([
            'nom' => 'Produit test',
            'prix' => $price,
            'stock' => $stock,
            'categorie_id' => $categorie->id,
        ]);
    }

    private function orderPayload(array $items): array
    {
        return [
            'items' => $items,
            'payment_method' => 'credit_card',
            'delivery_address' => '123 Test Street',
            'delivery_phone' => '+212600000000',
        ];
    }

    private function orderWithReservedStock(): array
    {
        $admin = $this->user('admin');
        $client = $this->user();
        $produit = $this->product();
        $commande = Commande::create([
            'user_id' => $client->id,
            'statut' => 'en_cours',
            'total' => 20,
            'payment_method' => 'credit_card',
            'delivery_address' => '123 Test Street',
            'delivery_phone' => '+212600000000',
            'estimated_delivery_date' => now()->addDays(3),
        ]);
        $commande->details()->create([
            'produit_id' => $produit->id,
            'quantite' => 2,
            'prix_unitaire' => 10,
        ]);
        $produit->decrement('stock', 2);

        return [$admin, $commande, $produit];
    }
}
