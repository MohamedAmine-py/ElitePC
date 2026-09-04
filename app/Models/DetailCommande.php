<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
 * DetailCommande — a single line in an order.
 * Example: "2x iPhone 15 at 999.99 each"
 * This is the JOIN table between commandes and produits.
 */
class DetailCommande extends Model
{
    protected $table = 'details_commandes';

    protected $fillable = [
        'commande_id',
        'produit_id',
        'quantite',
        'prix_unitaire',
    ];

    // Which order this line belongs to
    public function commande()
    {
        return $this->belongsTo(Commande::class, 'commande_id');
    }

    // Which product this line refers to
    public function produit()
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }
}
