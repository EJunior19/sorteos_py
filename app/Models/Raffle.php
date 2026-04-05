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
        'image',
        'winner_number',
        'winner_name',
    ];

    public function numbers()
    {
        return $this->hasMany(RaffleNumber::class);
    }
}