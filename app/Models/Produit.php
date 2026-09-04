<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
 * Produit model — represents a product in the store.
 * Belongs to one category.
 * Can appear in many order details.
 *
 * Hardware spec fields (nullable):
 *   brand, processor, graphics_card, ram_details, storage_details, is_custom_build
 */
class Produit extends Model
{
    protected $table = 'produits';

    protected $fillable = [
        'nom',
        'description',
        'prix',
        'stock',
        'image',
        'categorie_id',
        // Hardware specification columns
        'brand',
        'processor',
        'graphics_card',
        'ram_details',
        'storage_details',
        'is_custom_build',
    ];

    protected $casts = [
        'is_custom_build' => 'boolean',
        'prix'            => 'float',
        'stock'           => 'integer',
    ];

    // Each product belongs to one category
    public function categorie()
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    // A product can appear in many order detail lines
    public function detailsCommandes()
    {
        return $this->hasMany(DetailCommande::class, 'produit_id');
    }
}
