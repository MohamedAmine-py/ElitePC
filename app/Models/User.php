<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/*
 * User model — represents a person using the app.
 * Extends Authenticatable so Laravel knows this is a "login-able" model.
 * HasApiTokens adds Sanctum token support (used for login/logout).
 */
class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    // $fillable = columns we allow to be mass-assigned (e.g. User::create([...]))
    // Never put sensitive things like 'role' here if you don't want users setting it themselves
    protected $fillable = [
        'nom',
        'email',
        'mot_de_passe',
        'role',
    ];

    // $hidden = columns that will NEVER appear in API responses (JSON)
    protected $hidden = [
        'mot_de_passe',
    ];

    // Tell Laravel our password column is 'mot_de_passe' not 'password'
    protected string $passwordColumn = 'mot_de_passe';

    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    // One user can have many orders
    public function commandes()
    {
        return $this->hasMany(Commande::class, 'user_id');
    }
}
