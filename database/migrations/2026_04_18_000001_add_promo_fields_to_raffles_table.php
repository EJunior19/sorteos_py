<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raffles', function (Blueprint $table) {
            $table->boolean('promo_enabled')->default(false)->after('alias');
            $table->string('promo_type')->nullable()->after('promo_enabled');
            $table->unsignedInteger('promo_limit')->nullable()->after('promo_type');
            $table->unsignedInteger('promo_winner_count')->default(0)->after('promo_limit');
            $table->string('promo_prize_text')->nullable()->after('promo_winner_count');
        });
    }

    public function down(): void
    {
        Schema::table('raffles', function (Blueprint $table) {
            $table->dropColumn([
                'promo_enabled',
                'promo_type',
                'promo_limit',
                'promo_winner_count',
                'promo_prize_text',
            ]);
        });
    }
};
