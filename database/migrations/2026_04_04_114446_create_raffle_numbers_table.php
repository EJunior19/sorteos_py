<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('raffle_numbers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('raffle_id')->constrained()->onDelete('cascade');

            $table->string('number', 10);

            $table->enum('status', ['free', 'reserved', 'sold'])->default('free');

            $table->string('customer_name')->nullable();

            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->boolean('paid')->default(false);

            $table->timestamps();

            // 🔒 evita duplicados
            $table->unique(['raffle_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_numbers');
    }
};
