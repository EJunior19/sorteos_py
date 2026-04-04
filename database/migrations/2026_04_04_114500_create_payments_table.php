<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('raffle_number_id')->constrained()->onDelete('cascade');

            $table->decimal('amount', 12, 2);

            $table->string('payment_method')->nullable();
            $table->string('proof_image')->nullable();

            $table->boolean('confirmed')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
