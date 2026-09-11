<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['produit_id', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
}
