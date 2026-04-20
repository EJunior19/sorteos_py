<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raffle_promo_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raffle_id')
                  ->constrained('raffles')
                  ->cascadeOnDelete();
            $table->foreignId('raffle_number_id')
                  ->constrained('raffle_numbers')
                  ->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('prize_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_promo_results');
    }
};
