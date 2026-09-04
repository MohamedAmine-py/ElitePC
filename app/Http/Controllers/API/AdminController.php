<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/*
 * AdminController — provides dashboard statistics for the admin panel.
 */
class AdminController extends Controller
{
    // GET /api/admin/stats
    public function stats()
    {
        return response()->json([
            'total_users'     => User::where('role', 'client')->count(),
            'total_produits'  => Produit::count(),
            'total_commandes' => Commande::count(),
            'commandes_en_cours' => Commande::where('statut', 'en_cours')->count(),
            'revenus_total'   => Commande::where('statut', 'validee')->sum('total'),
            'recent_orders'   => Commande::with('user')->latest()->take(5)->get(),
        ]);
    }

    // GET /api/admin/users
    public function users()
    {
        $users = User::withCount('commandes')->latest()->get();
        return response()->json($users);
    }

    // POST /api/admin/users
    public function storeUser(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'mot_de_passe' => 'required|string|min:6',
            'role' => 'required|in:admin,client',
        ]);

        $user = User::create([
            'nom' => $request->nom,
            'email' => $request->email,
            'mot_de_passe' => Hash::make($request->mot_de_passe),
            'role' => $request->role,
        ]);

        return response()->json($user, 201);
    }

    // PUT /api/admin/users/{id}
    public function updateUser(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non trouvé'], 404);
        }

        $request->validate([
            'nom' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,'.$id,
            'mot_de_passe' => 'nullable|string|min:6',
            'role' => 'sometimes|in:admin,client',
        ]);

        if ($request->has('nom')) {
            $user->nom = $request->nom;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->filled('mot_de_passe')) {
            $user->mot_de_passe = Hash::make($request->mot_de_passe);
        }
        if ($request->has('role')) {
            $user->role = $request->role;
        }

        $user->save();

        return response()->json($user);
    }

    // DELETE /api/admin/users/{id}
    public function destroyUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non trouvé'], 404);
        }

        // Check if we are trying to delete the current user (if needed, but usually we just let it happen or prevent it in UI)
        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé']);
    }
}
