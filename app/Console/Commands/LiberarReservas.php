<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RaffleNumber;

class LiberarReservas extends Command
{
    protected $signature = 'reservas:liberar';
    protected $description = 'Libera números reservados que expiraron';

    public function handle()
    {
        $numeros = RaffleNumber::where('status', 'reserved')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($numeros as $n) {
            $n->update([
                'status' => 'free',
                'customer_name' => null,
                'reserved_at' => null,
                'expires_at' => null,
            ]);
        }

        $this->info('Reservas liberadas: ' . $numeros->count());
    }
}