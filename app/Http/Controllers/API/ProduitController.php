<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use Illuminate\Http\Request;

/*
 * ProduitController — handles all product CRUD operations.
 *
 * Public routes  : index (list), show (one product)
 * Admin routes   : store (create), update (edit), destroy (delete)
 *
 * Now supports hardware specification fields:
 *   brand, processor, graphics_card, ram_details, storage_details, is_custom_build
 */
class ProduitController extends Controller
{
    // GET /api/products — list all products with their category
    public function index(Request $request)
    {
        $query = Produit::with('categorie');

        // Optional filter by category
        if ($request->has('categorie_id')) {
            $query->where('categorie_id', $request->categorie_id);
        }

        // Optional search by name or brand
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nom', 'like', '%' . $request->search . '%')
                  ->orWhere('brand', 'like', '%' . $request->search . '%')
                  ->orWhere('processor', 'like', '%' . $request->search . '%')
                  ->orWhere('graphics_card', 'like', '%' . $request->search . '%');
            });
        }

        $produits = $query->paginate(50);

        return response()->json($produits);
    }

    // GET /api/products/{id} — get one product
    public function show($id)
    {
        $produit = Produit::with('categorie')->find($id);

        if (!$produit) {
            return response()->json(['message' => 'Produit non trouvé'], 404);
        }

        return response()->json($produit);
    }

    // POST /api/products — create a product (admin only)
    public function store(Request $request)
    {
        $request->validate([
            'nom'             => 'required|string|max:200',
            'prix'            => 'required|numeric|min:0',
            'stock'           => 'required|integer|min:0',
            'categorie_id'    => 'required|exists:categories,id',
            'description'     => 'nullable|string',
            'image'           => 'nullable|string',
            // Hardware spec fields
            'brand'           => 'nullable|string|max:100',
            'processor'       => 'nullable|string|max:200',
            'graphics_card'   => 'nullable|string|max:200',
            'ram_details'     => 'nullable|string|max:200',
            'storage_details' => 'nullable|string|max:200',
            'is_custom_build' => 'nullable|boolean',
        ]);

        $produit = Produit::create($request->all());

        return response()->json($produit, 201);
    }

    // PUT /api/products/{id} — update a product (admin only)
    public function update(Request $request, $id)
    {
        $produit = Produit::find($id);

        if (!$produit) {
            return response()->json(['message' => 'Produit non trouvé'], 404);
        }

        $request->validate([
            'nom'             => 'sometimes|string|max:200',
            'prix'            => 'sometimes|numeric|min:0',
            'stock'           => 'sometimes|integer|min:0',
            'categorie_id'    => 'sometimes|exists:categories,id',
            'description'     => 'nullable|string',
            'image'           => 'nullable|string',
            // Hardware spec fields
            'brand'           => 'nullable|string|max:100',
            'processor'       => 'nullable|string|max:200',
            'graphics_card'   => 'nullable|string|max:200',
            'ram_details'     => 'nullable|string|max:200',
            'storage_details' => 'nullable|string|max:200',
            'is_custom_build' => 'nullable|boolean',
        ]);

        $produit->update($request->all());

        return response()->json($produit);
    }

    // DELETE /api/products/{id} — delete a product (admin only)
    public function destroy($id)
    {
        $produit = Produit::find($id);

        if (!$produit) {
            return response()->json(['message' => 'Produit non trouvé'], 404);
        }

        $produit->delete();

        return response()->json(['message' => 'Produit supprimé']);
    }
}
