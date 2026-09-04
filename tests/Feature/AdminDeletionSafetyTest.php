<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDeletionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->user('admin');
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$admin->id}")->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_user_with_historical_orders_cannot_be_deleted(): void
    {
        $admin = $this->user('admin');
        $client = $this->user();
        $order = $this->order($client);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$client->id}")->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $client->id]);
        $this->assertDatabaseHas('commandes', ['id' => $order->id]);
    }

    public function test_product_referenced_by_an_order_cannot_be_deleted(): void
    {
        $admin = $this->user('admin');
        $client = $this->user();
        $category = Categorie::create(['nom' => 'Components']);
        $product = Produit::create([
            'nom' => 'Historical component',
            'prix' => 100,
            'stock' => 4,
            'categorie_id' => $category->id,
        ]);
        $order = $this->order($client);
        $order->details()->create([
            'produit_id' => $product->id,
            'quantite' => 1,
            'prix_unitaire' => 100,
        ]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/products/{$product->id}")->assertUnprocessable();

        $this->assertDatabaseHas('produits', ['id' => $product->id]);
        $this->assertDatabaseHas('details_commandes', ['commande_id' => $order->id, 'produit_id' => $product->id]);
    }

    public function test_non_empty_category_cannot_be_deleted(): void
    {
        $admin = $this->user('admin');
        $category = Categorie::create(['nom' => 'Peripherals']);
        $product = Produit::create([
            'nom' => 'Keyboard',
            'prix' => 100,
            'stock' => 4,
            'categorie_id' => $category->id,
        ]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/categories/{$category->id}")->assertUnprocessable();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('produits', ['id' => $product->id]);
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

    private function order(User $user): Commande
    {
        return Commande::create([
            'user_id' => $user->id,
            'statut' => 'validee',
            'total' => 100,
            'payment_method' => 'credit_card',
            'delivery_address' => '123 Test Street',
            'delivery_phone' => '+212600000000',
            'estimated_delivery_date' => now()->addDays(3),
        ]);
    }
}
