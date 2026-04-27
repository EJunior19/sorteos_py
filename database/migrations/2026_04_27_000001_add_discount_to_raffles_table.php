<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('raffles', function (Blueprint $table) {
            $table->boolean('discount_active')->default(false)->after('alias');
            $table->unsignedTinyInteger('discount_pct')->default(0)->after('discount_active');
        });
    }

    public function down(): void
    {
        Schema::table('raffles', function (Blueprint $table) {
            $table->dropColumn(['discount_active', 'discount_pct']);
        });
    }
};
