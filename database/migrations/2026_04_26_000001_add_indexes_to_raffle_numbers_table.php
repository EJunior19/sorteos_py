<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raffle_numbers', function (Blueprint $table) {
            // Búsquedas por sorteo + estado (las más frecuentes)
            $table->index(['raffle_id', 'status'], 'idx_raffle_numbers_raffle_status');

            // Ordenamiento por fecha de reserva (para promo y listados)
            $table->index(['raffle_id', 'reserved_at'], 'idx_raffle_numbers_reserved_at');
        });
    }

    public function down(): void
    {
        Schema::table('raffle_numbers', function (Blueprint $table) {
            $table->dropIndex('idx_raffle_numbers_raffle_status');
            $table->dropIndex('idx_raffle_numbers_reserved_at');
        });
    }
};
