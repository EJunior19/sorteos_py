<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaffleCandidateAnalysis extends Model
{
    protected $fillable = [
        'batch_uuid',
        'source_file',
        'selection_group',
        'product_name',
        'category',
        'raffle_type',
        'cost_gs',
        'stock',
        'numbers_count',
        'price_per_number_gs',
        'revenue_gs',
        'estimated_profit_gs',
        'score',
        'risk_level',
        'reason',
        'historical_comparison',
        'filter_status',
        'filter_reasons',
        'metrics',
        'approved_at',
        'created_raffle_id',
    ];

    protected $casts = [
        'filter_reasons' => 'array',
        'metrics' => 'array',
        'approved_at' => 'datetime',
        'score' => 'decimal:2',
    ];

    public function createdRaffle()
    {
        return $this->belongsTo(Raffle::class, 'created_raffle_id');
    }
}
