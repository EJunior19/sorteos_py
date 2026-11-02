<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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

    /**
     * Devuelve el número a "free" y borra los datos del participante.
     * Antes deja en el log quién lo tenía. El llamador debe tener el lock
     * de la fila (lockForUpdate dentro de una transacción).
     */
    public function liberar(string $motivo): void
    {
        Log::info("🔓 RESERVA LIBERADA ({$motivo}) — sorteo #{$this->raffle_id}, número {$this->number}", [
            'raffle_number_id' => $this->id,
            'customer_name'    => $this->customer_name,
            'reserved_at'      => $this->reserved_at?->toDateTimeString(),
            'expires_at'       => $this->expires_at?->toDateTimeString(),
        ]);

        $this->update([
            'status'        => 'free',
            'customer_name' => null,
            'reserved_at'   => null,
            'expires_at'    => null,
            'paid'          => false,
        ]);
    }
}
