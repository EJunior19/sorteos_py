<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaffleNumber extends Model
{
    protected $fillable = [
        'raffle_id',
        'number',
        'status',
        'customer_name',
        'reserved_at',
        'expires_at',
        'paid',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
        'paid' => 'boolean',
    ];

    public function raffle()
    {
        return $this->belongsTo(Raffle::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
