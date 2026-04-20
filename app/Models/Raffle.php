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
        'prizes_count',
        'titular_name',
        'alias',
        'promo_enabled',
        'promo_type',
        'promo_limit',
        'promo_winner_count',
        'promo_prize_text',
    ];

    protected $casts = [
        'promo_enabled' => 'boolean',
    ];

    public function numbers()
    {
        return $this->hasMany(RaffleNumber::class);
    }

    public function prizes()
    {
        return $this->hasMany(RafflePrize::class)->orderBy('order');
    }

    public function promoResults()
    {
        return $this->hasMany(RafflePromoResult::class);
    }

    /**
     * Indica si el sorteo usa el sistema de múltiples premios.
     * Los sorteos legacy (prizes_count = 1 sin prizes en BD) usan winner_number/winner_name.
     */
    public function usesMultiplePrizes(): bool
    {
        return $this->prizes_count > 1 || $this->prizes()->exists();
    }
}