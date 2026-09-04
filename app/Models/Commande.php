<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
 * Commande model — represents a customer order.
 * Belongs to a user.
 * Has many detail lines (one per product ordered).
 */
class Commande extends Model
{
    protected $table = 'commandes';

    protected $fillable = [
        'user_id',
        'statut',
        'total',
        'payment_method',
        'delivery_address',
        'delivery_phone',
        'estimated_delivery_date',
    ];

    // The customer who placed this order
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // The list of products in this order
    public function details()
    {
        return $this->hasMany(DetailCommande::class, 'commande_id');
    }
}
