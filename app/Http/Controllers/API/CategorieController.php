<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Categorie;
use Illuminate\Http\Request;

class CategorieController extends Controller
{
    // GET /api/categories — public
    public function index()
    {
        // withCount('produits') adds a produits_count field automatically
        $categories = Categorie::withCount('produits')->get();

        return response()->json($categories);
    }

    // POST /api/categories — admin only
    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:100|unique:categories,nom',
            'description' => 'nullable|string',
        ]);

        $categorie = Categorie::create($request->all());

        return response()->json($categorie, 201);
    }

    // PUT /api/categories/{id} — admin only
    public function update(Request $request, $id)
    {
        $categorie = Categorie::find($id);

        if (! $categorie) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $categorie->update($request->all());

        return response()->json($categorie);
    }

    // DELETE /api/categories/{id} — admin only
    public function destroy($id)
    {
        $categorie = Categorie::find($id);

        if (! $categorie) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        if ($categorie->produits()->exists()) {
            return response()->json(['message' => 'Categories containing products cannot be deleted'], 422);
        }

        $categorie->delete();

        return response()->json(['message' => 'Category deleted']);
    }
}
