<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Commande;
use App\Models\Produit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/*
 * CommandeController — handles orders.
 *
 * HOW AN ORDER WORKS:
 * 1. Client sends a list of products + quantities
 * 2. We check stock for each product
 * 3. We calculate the total
 * 4. We create the commande + all detail lines
 * 5. We deduct stock from each product
 */
class CommandeController extends Controller
{
    // GET /api/orders
    // Admin sees all orders, client sees only their own
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            $commandes = Commande::with(['user', 'details.produit'])->latest()->get();
        } else {
            $commandes = Commande::with(['details.produit'])
                ->where('user_id', $user->id)
                ->latest()
                ->get();
        }

        return response()->json($commandes);
    }

    // GET /api/orders/{id}
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $commande = Commande::with(['details.produit', 'user'])->find($id);

        if (! $commande) {
            return response()->json(['message' => 'Commande non trouvée'], 404);
        }

        // Client can only see their own orders
        if ($user->role !== 'admin' && $commande->user_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return response()->json($commande);
    }

    // POST /api/orders — place an order
    // Expected body: { "items": [...], "payment_method": "credit_card", "delivery_address": "...", "delivery_phone": "..." }
    public function store(StoreOrderRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $items = collect($request->items);
            $requestedQuantities = $items
                ->groupBy('produit_id')
                ->map(fn ($productItems) => $productItems->sum('quantite'));

            // Lock in a stable order so concurrent checkouts cannot oversell stock.
            $produits = Produit::query()
                ->whereIn('id', $requestedQuantities->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($requestedQuantities as $produitId => $quantite) {
                $produit = $produits->get($produitId);

                if ($produit->stock < $quantite) {
                    return response()->json([
                        'message' => "Stock insuffisant pour : {$produit->nom}",
                    ], 422);
                }
            }

            $total = 0;
            $lines = [];

            // Prices always come from the locked server-side product rows.
            foreach ($items as $item) {
                $produit = $produits->get($item['produit_id']);
                $subtotal = $produit->prix * $item['quantite'];
                $total += $subtotal;

                $lines[] = [
                    'produit' => $produit,
                    'quantite' => $item['quantite'],
                    'prix_unitaire' => $produit->prix,
                ];
            }

            $commande = Commande::create([
                'user_id' => $request->user()->id,
                'statut' => 'en_cours',
                'total' => $total,
                'payment_method' => $request->payment_method,
                'delivery_address' => $request->delivery_address,
                'delivery_phone' => $request->delivery_phone,
                'estimated_delivery_date' => $this->calculateDeliveryDate(),
            ]);

            foreach ($lines as $line) {
                $commande->details()->create([
                    'produit_id' => $line['produit']->id,
                    'quantite' => $line['quantite'],
                    'prix_unitaire' => $line['prix_unitaire'],
                ]);
            }

            foreach ($requestedQuantities as $produitId => $quantite) {
                $produits->get($produitId)->decrement('stock', $quantite);
            }

            return response()->json($commande->load('details.produit'), 201);
        });
    }

    // Helper function to calculate delivery date (3 business days)
    private function calculateDeliveryDate()
    {
        $date = now();
        $businessDays = 0;

        while ($businessDays < 3) {
            $date->addDay();
            // 0 = Sunday, 6 = Saturday
            if ($date->dayOfWeek !== 0 && $date->dayOfWeek !== 6) {
                $businessDays++;
            }
        }

        return $date->toDateString();
    }

    // PATCH /api/orders/{id}/status — admin only
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:en_cours,validee,annulee',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $commande = Commande::lockForUpdate()->find($id);

            if (! $commande) {
                return response()->json(['message' => 'Commande non trouvée'], 404);
            }

            $details = $commande->details()->orderBy('produit_id')->get();
            $quantities = $details->groupBy('produit_id')
                ->map(fn ($productDetails) => $productDetails->sum('quantite'));
            $produits = Produit::query()
                ->whereIn('id', $quantities->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($commande->statut !== 'annulee' && $request->statut === 'annulee') {
                foreach ($quantities as $produitId => $quantite) {
                    $produits->get($produitId)?->increment('stock', $quantite);
                }
            } elseif ($commande->statut === 'annulee' && $request->statut !== 'annulee') {
                foreach ($quantities as $produitId => $quantite) {
                    $produit = $produits->get($produitId);
                    if (! $produit || $produit->stock < $quantite) {
                        return response()->json([
                            'message' => $produit
                                ? "Stock insuffisant pour : {$produit->nom}"
                                : 'Un produit de cette commande n’existe plus',
                        ], 422);
                    }
                }

                foreach ($quantities as $produitId => $quantite) {
                    $produits->get($produitId)->decrement('stock', $quantite);
                }
            }

            $commande->update(['statut' => $request->statut]);

            return response()->json($commande);
        });
    }

    // DELETE /api/orders/{id} — admin only
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $commande = Commande::lockForUpdate()->find($id);

            if (! $commande) {
                return response()->json(['message' => 'Commande non trouvée'], 404);
            }

            $details = $commande->details()->orderBy('produit_id')->get();

            // Cancelled orders were already restored when their status changed.
            if ($commande->statut !== 'annulee') {
                $quantities = $details->groupBy('produit_id')
                    ->map(fn ($productDetails) => $productDetails->sum('quantite'));
                $produits = Produit::query()
                    ->whereIn('id', $quantities->keys())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($quantities as $produitId => $quantite) {
                    $produits->get($produitId)?->increment('stock', $quantite);
                }
            }

            $commande->delete();

            return response()->json(['message' => 'Commande supprimée']);
        });
    }

    // GET /api/orders/{id}/invoice — download invoice as PDF
    public function downloadInvoice(Request $request, $id)
    {
        $user = $request->user();
        $commande = Commande::with(['details.produit', 'user'])->find($id);

        if (! $commande) {
            return response()->json(['message' => 'Commande non trouvée'], 404);
        }

        // Client can only download their own invoices
        if ($user->role !== 'admin' && $commande->user_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Generate a print-ready A4 invoice from the authoritative stored order.
        $pdf = Pdf::loadView('invoices.invoice', [
            'commande' => $commande,
        ])->setPaper('a4');

        return $pdf->download("Invoice-{$commande->id}.pdf");
    }
}
