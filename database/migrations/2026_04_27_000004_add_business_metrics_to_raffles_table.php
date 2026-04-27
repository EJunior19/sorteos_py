<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('raffles', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
            $table->string('original_product')->nullable()->after('category');
            $table->string('raffle_type')->nullable()->after('original_product');
            $table->unsignedBigInteger('cost_gs')->default(0)->after('total_numbers');
            $table->bigInteger('real_profit_gs')->nullable()->after('cost_gs');
            $table->timestamp('sale_started_at')->nullable()->after('real_profit_gs');
            $table->timestamp('sold_out_at')->nullable()->after('sale_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('raffles', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'original_product',
                'raffle_type',
                'cost_gs',
                'real_profit_gs',
                'sale_started_at',
                'sold_out_at',
            ]);
        });
    }
};
