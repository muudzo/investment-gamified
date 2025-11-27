<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'xp_reward',
        'requirement_type',
        'requirement_value',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps()
            ->withPivot('unlocked_at');
    }
}
