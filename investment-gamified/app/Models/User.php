<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User model for the investment-gamified system.
 * 
 * CRITICAL: All persisted monetary values use DECIMAL(10,2) casting at the DB layer.
 * PHP Eloquent casts balance to 'decimal:2' automatically.
 * 
 * MONEY HANDLING RULE:
 * - balance: always a Decimal object (via Eloquent cast), NEVER a PHP float
 * - Never perform balance mutations outside PortfolioService.buyStock()/sellStock()
 * - All balance arithmetic must occur in SQL (DB::raw expressions) to preserve precision
 * - Client-side calculations must use standard financial precision (scale=2)
 * 
 * See: PRODUCTION_SCALE_FIXES_GUIDE.md "Explicit Money Representation Contract"
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class)
            ->withTimestamps()
            ->withPivot('unlocked_at');
    }
}
