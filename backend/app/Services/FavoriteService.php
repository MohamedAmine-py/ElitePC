<?php

namespace App\Services;

use App\Models\Produit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FavoriteService
{
    public function read(User $user): array
    {
        $items = $user->favorites()->with('produit.categorie')->orderBy('id')->get()
            ->filter(fn ($favorite) => $favorite->produit !== null)
            ->map(fn ($favorite) => array_merge($favorite->produit->only([
                'id', 'nom', 'description', 'image', 'prix', 'stock', 'categorie_id',
                'brand', 'processor', 'graphics_card', 'ram_details', 'storage_details', 'is_custom_build',
            ]), ['categorie' => $favorite->produit->categorie?->only(['id', 'nom'])]))
            ->values()->all();

        return ['items' => $items];
    }

    public function add(User $user, int $productId): array
    {
        return DB::transaction(function () use ($user, $productId) {
            // Serialize writes even when this user's favorites are initially empty.
            User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            Produit::whereKey($productId)->lockForUpdate()->firstOrFail();
            $user->favorites()->firstOrCreate(['produit_id' => $productId]);

            return $this->read($user);
        }, 3);
    }

    public function remove(User $user, int $productId): array
    {
        return DB::transaction(function () use ($user, $productId) {
            User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            // Already absent or deleted products are harmless, as with cart removal.
            $user->favorites()->where('produit_id', $productId)->delete();

            return $this->read($user);
        }, 3);
    }
}
