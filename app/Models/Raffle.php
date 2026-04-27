<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Raffle extends Model
{
    protected $fillable = [
        'name',
        'category',
        'original_product',
        'raffle_type',
        'price',
        'total_numbers',
        'cost_gs',
        'real_profit_gs',
        'sale_started_at',
        'sold_out_at',
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
        'discount_active',
        'discount_pct',
        'urgency_messages',
    ];

    protected $casts = [
        'promo_enabled'   => 'boolean',
        'discount_active' => 'boolean',
        'urgency_messages' => 'array',
        'sale_started_at' => 'datetime',
        'sold_out_at'     => 'datetime',
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
