<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'raffle_number_id',
        'amount',
        'payment_method',
        'proof_image',
        'confirmed',
    ];

    protected $casts = [
        'confirmed' => 'boolean',
    ];

    public function raffleNumber()
    {
        return $this->belongsTo(RaffleNumber::class);
    }
}
