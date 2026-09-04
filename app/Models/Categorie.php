<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
 * Categorie model — represents a product category (e.g. Smartphones, Laptops).
 * A category has many products.
 */
class Categorie extends Model
{
    protected $table = 'categories';

    protected $fillable = ['nom', 'description'];

    // One category has many products
    public function produits()
    {
        return $this->hasMany(Produit::class, 'categorie_id');
    }
}
