<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'balance',
        'level',
        'experience_points',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'balance' => 'decimal:2',
    ];

    public function portfolios()
    {
        return $this->hasMany(Portfolio::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function achievements()
    {
        return $this->belongsToMany(Achievement::class)
            ->withTimestamps()
            ->withPivot('unlocked_at');
    }
}

// app/Models/Stock.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = [
        'symbol',
        'name',
        'description',
        'kid_friendly_description',
        'fun_fact',
        'category',
        'current_price',
        'change_percentage',
        'logo_url',
    ];

    protected $casts = [
        'current_price' => 'decimal:2',
        'change_percentage' => 'decimal:2',
    ];

    public function portfolios()
    {
        return $this->hasMany(Portfolio::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function history()
    {
        return $this->hasMany(StockHistory::class);
    }
}