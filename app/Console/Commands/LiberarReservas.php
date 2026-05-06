<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
class LiberarReservas extends Command
{
    protected $signature = 'reservas:liberar';
    protected $description = 'Comando desactivado: las reservas ya no se liberan automaticamente';

    public function handle()
    {
        $this->info('Liberacion automatica desactivada. No se modificaron reservas.');
        return self::SUCCESS;
    }
}
