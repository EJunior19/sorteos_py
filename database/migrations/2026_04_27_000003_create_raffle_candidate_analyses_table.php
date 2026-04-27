<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('raffle_candidate_analyses', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_uuid')->index();
            $table->string('source_file')->nullable();
            $table->string('selection_group')->index();
            $table->string('product_name');
            $table->string('category')->index();
            $table->string('raffle_type')->index();
            $table->unsignedBigInteger('cost_gs');
            $table->unsignedInteger('stock')->nullable();
            $table->unsignedSmallInteger('numbers_count');
            $table->unsignedBigInteger('price_per_number_gs');
            $table->unsignedBigInteger('revenue_gs');
            $table->bigInteger('estimated_profit_gs');
            $table->decimal('score', 8, 2);
            $table->string('risk_level');
            $table->text('reason');
            $table->text('historical_comparison')->nullable();
            $table->string('filter_status')->default('selected')->index();
            $table->json('filter_reasons')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_raffle_id')->nullable()->constrained('raffles')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_candidate_analyses');
    }
};
