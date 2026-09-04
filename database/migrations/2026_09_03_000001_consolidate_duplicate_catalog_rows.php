<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $duplicateNames = DB::table('produits')
                ->select('nom')
                ->groupBy('nom')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('nom');

            foreach ($duplicateNames as $name) {
                $products = DB::table('produits')
                    ->where('nom', $name)
                    ->orderBy('id')
                    ->get();

                $referenceCounts = DB::table('details_commandes')
                    ->whereIn('produit_id', $products->pluck('id'))
                    ->select('produit_id', DB::raw('COUNT(*) as references_count'))
                    ->groupBy('produit_id')
                    ->pluck('references_count', 'produit_id');

                $canonical = $products
                    ->sortByDesc(fn ($product) => $referenceCounts[$product->id] ?? 0)
                    ->first();
                $duplicateIds = $products->pluck('id')->reject(fn ($id) => $id === $canonical->id);

                DB::table('details_commandes')
                    ->whereIn('produit_id', $duplicateIds)
                    ->update(['produit_id' => $canonical->id]);
                DB::table('produits')->whereIn('id', $duplicateIds)->delete();
            }

            $duplicateCategories = DB::table('categories')
                ->select('nom')
                ->groupBy('nom')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('nom');

            foreach ($duplicateCategories as $name) {
                $categories = DB::table('categories')->where('nom', $name)->orderBy('id')->get();
                $productCounts = DB::table('produits')
                    ->whereIn('categorie_id', $categories->pluck('id'))
                    ->select('categorie_id', DB::raw('COUNT(*) as products_count'))
                    ->groupBy('categorie_id')
                    ->pluck('products_count', 'categorie_id');
                $canonical = $categories
                    ->sortByDesc(fn ($category) => $productCounts[$category->id] ?? 0)
                    ->first();
                $duplicateIds = $categories->pluck('id')->reject(fn ($id) => $id === $canonical->id);

                DB::table('produits')
                    ->whereIn('categorie_id', $duplicateIds)
                    ->update(['categorie_id' => $canonical->id]);
                DB::table('categories')->whereIn('id', $duplicateIds)->delete();
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unique('nom', 'categories_nom_unique');
        });
        Schema::table('produits', function (Blueprint $table) {
            $table->unique('nom', 'produits_nom_unique');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropUnique('produits_nom_unique');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_nom_unique');
        });
    }
};
