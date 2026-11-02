<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('raffles', function (Blueprint $table) {
            $table->boolean('weighted_draw_enabled')->default(false)->after('urgency_messages');
            $table->json('win_weights')->nullable()->after('weighted_draw_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('raffles', function (Blueprint $table) {
            $table->dropColumn(['weighted_draw_enabled', 'win_weights']);
        });
    }
};
