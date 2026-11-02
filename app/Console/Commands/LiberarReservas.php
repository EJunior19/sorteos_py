<?php

namespace App\Console\Commands;

use App\Models\RaffleNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LiberarReservas extends Command
{
    protected $signature = 'reservas:liberar {--force : Liberar aunque SORTEOS_LIBERACION_AUTOMATICA esté desactivado}';
    protected $description = 'Devuelve a "free" las reservas sin pagar con expires_at vencido (solo sorteos activos)';

    public function handle()
    {
        if (!config('sorteos.liberacion_automatica') && !$this->option('force')) {
            $this->info('Liberación automática desactivada (SORTEOS_LIBERACION_AUTOMATICA=false). No se modificaron reservas.');
            return self::SUCCESS;
        }

        // Las reservas viejas tienen expires_at = null y nunca se liberan solas.
        $ids = RaffleNumber::where('status', 'reserved')
            ->where('paid', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereHas('raffle', fn ($q) => $q->where('status', 'active'))
            ->orderBy('id')
            ->pluck('id');

        $liberados = 0;
        $raffleIds = [];

        foreach ($ids->chunk(100) as $chunk) {
            DB::transaction(function () use ($chunk, &$liberados, &$raffleIds) {
                $nums = RaffleNumber::whereIn('id', $chunk)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($nums as $n) {
                    // Revalidar con el lock: pudo confirmarse el pago mientras tanto.
                    if ($n->status !== 'reserved' || $n->paid || !$n->expires_at || $n->expires_at->isFuture()) {
                        continue;
                    }

                    $n->liberar('vencida');
                    $liberados++;
                    $raffleIds[$n->raffle_id] = true;
                }
            });
        }

        foreach (array_keys($raffleIds) as $raffleId) {
            Cache::forget("dashboard_stats_{$raffleId}");
        }

        $this->info("Reservas liberadas: {$liberados}");

        return self::SUCCESS;
    }
}
