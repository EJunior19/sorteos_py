<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Raffle extends Model
{
    protected $fillable = [
        'name',
        'price',
        'total_numbers',
        'draw_date',
        'status',
        'image', // 🔥 FIX IMPORTANTE
    ];

    // 🔗 RELACIÓN CON NÚMEROS
    public function numbers()
    {
        return $this->hasMany(RaffleNumber::class);
    }
}