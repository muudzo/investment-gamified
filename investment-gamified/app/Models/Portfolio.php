<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Portfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stock_id',
        'quantity',
        'average_price',
    ];

    protected $casts = [
        'average_price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}
