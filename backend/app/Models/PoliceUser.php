<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class PoliceUser extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'police_users';

    protected $fillable = [
        'username', 'password_hash', 'name', 'badge_number',
        'jurisdiction_state', 'jurisdiction_district', 'role', 'last_login_at',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = ['last_login_at' => 'datetime'];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }
}