<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RafflePromoResult extends Model
{
    protected $fillable = [
        'raffle_id',
        'raffle_number_id',
        'customer_name',
        'prize_text',
    ];

    public function raffle()
    {
        return $this->belongsTo(Raffle::class);
    }

    public function raffleNumber()
    {
        return $this->belongsTo(RaffleNumber::class);
    }
}
