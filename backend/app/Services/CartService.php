<?php

namespace App\Services;

use App\Models\Produit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    // Match existing checkout limits, not a stock reservation policy.
    public const MAX_QUANTITY = 100;

    public const MAX_ITEMS = 50;

    /** Caller must be inside a transaction. Serialize even an initially empty cart. */
    public function lockUser(User $user): void
    {
        User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
    }

    public function read(User $user): array
    {
        $totalCents = 0;
        $items = $user->cartItems()->with('produit.categorie')->orderBy('id')->get()
            ->filter(fn ($line) => $line->produit !== null)
            ->map(function ($line) use (&$totalCents) {
                $product = $line->produit;
                $priceCents = (int) round($product->prix * 100);
                $subtotalCents = $priceCents * $line->quantity;
                $totalCents += $subtotalCents;

                return array_merge($product->only([
                    'id', 'nom', 'description', 'image', 'stock', 'categorie_id',
                    'brand', 'processor', 'graphics_card', 'ram_details', 'storage_details', 'is_custom_build',
                ]), [
                    'prix' => number_format($priceCents / 100, 2, '.', ''),
                    'categorie' => $product->categorie?->only(['id', 'nom']),
                    'quantite' => $line->quantity,
                    'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
                    'cart_item_id' => $line->id,
                    'cart_updated_at' => $line->updated_at->format('Y-m-d H:i:s.u'),
                ]);
            })->values()->all();

        return ['items' => $items, 'currency' => 'USD', 'total' => number_format($totalCents / 100, 2, '.', '')];
    }

    public function add(User $user, int $productId, mixed $quantity): array
    {
        return $this->writeQuantity($user, $productId, $quantity, true);
    }

    public function update(User $user, int $productId, mixed $quantity): array
    {
        return $this->writeQuantity($user, $productId, $quantity, false);
    }

    private function writeQuantity(User $user, int $productId, mixed $quantity, bool $add): array
    {
        $this->validateQuantity($quantity);

        return DB::transaction(function () use ($user, $productId, $quantity, $add) {
            $this->lockUser($user);
            // Same lock order as checkout: user first, then product. No stock is changed.
            $product = Produit::whereKey($productId)->lockForUpdate()->firstOrFail();
            $line = $user->cartItems()->where('produit_id', $productId)->first();
            if (! $add && ! $line) {
                abort(404, 'Cart item not found');
            }
            $newQuantity = $quantity + ($add ? ($line?->quantity ?? 0) : 0);
            $this->validateQuantity($newQuantity);
            if ($newQuantity > $product->stock) {
                throw ValidationException::withMessages(['quantity' => 'The requested quantity exceeds current stock.']);
            }
            if (! $line && $user->cartItems()->count() >= self::MAX_ITEMS) {
                throw ValidationException::withMessages(['quantity' => 'A cart cannot exceed 50 different products.']);
            }
            if ($line) {
                // Advance even when the quantity is unchanged or the clock is frozen.
                // A later edit must never share the checkout snapshot's version.
                $stamp = $line->freshTimestamp();
                if ($stamp->lessThanOrEqualTo($line->updated_at)) {
                    $stamp = $line->updated_at->copy()->addMicrosecond();
                }
                $line->quantity = $newQuantity;
                $line->updated_at = $stamp;
                $line->save();
            } else {
                $user->cartItems()->create(['produit_id' => $productId, 'quantity' => $newQuantity]);
            }

            return $this->read($user);
        }, 3);
    }

    private function validateQuantity(mixed $quantity): void
    {
        if (! is_int($quantity) || $quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be a whole number between 1 and 100.']);
        }
    }

    public function remove(User $user, int $productId): array
    {
        return DB::transaction(function () use ($user, $productId) {
            $this->lockUser($user);
            // Removing an already absent item is harmless and idempotent.
            $user->cartItems()->where('produit_id', $productId)->delete();

            return $this->read($user);
        }, 3);
    }

    /** In the successful order transaction, with the user's cart lock held. */
    public function clearPurchased(User $user, array $purchasedItems): void
    {
        foreach ($purchasedItems as $item) {
            // Legacy callers without a snapshot retain their cart; never guess ownership/version.
            if (! isset($item['cart_item_id'], $item['cart_updated_at'])) {
                continue;
            }
            $user->cartItems()->whereKey($item['cart_item_id'])
                ->where('produit_id', $item['produit_id'])
                ->where('quantity', $item['quantite'])
                ->where('updated_at', $item['cart_updated_at'])
                ->delete();
        }
    }
}
