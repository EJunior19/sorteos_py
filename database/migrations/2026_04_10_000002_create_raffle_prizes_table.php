<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raffle_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raffle_id')->constrained('raffles')->cascadeOnDelete();
            // order: 1 = último premio (menor), N = 1er premio (mayor/principal)
            $table->unsignedTinyInteger('order');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('winner_number')->nullable();
            $table->string('winner_name')->nullable();
            $table->timestamps();

            $table->unique(['raffle_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_prizes');
    }
};
