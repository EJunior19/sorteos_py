<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RafflePrize extends Model
{
    protected $fillable = [
        'raffle_id',
        'order',
        'name',
        'description',
        'winner_number',
        'winner_name',
    ];

    public function raffle()
    {
        return $this->belongsTo(Raffle::class);
    }

    /**
     * Si el premio ya tiene ganador asignado.
     */
    public function hasWinner(): bool
    {
        return !is_null($this->winner_number);
    }

    /**
     * Etiqueta de posición legible: "1er Premio", "2do Premio", etc.
     */
    public function positionLabel(): string
    {
        return match ($this->order) {
            1 => '1er',
            2 => '2do',
            3 => '3er',
            default => $this->order . 'to',
        } . ' Premio';
    }
}
