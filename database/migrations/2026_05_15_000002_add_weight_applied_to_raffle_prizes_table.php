<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('raffle_prizes', function (Blueprint $table) {
            $table->decimal('weight_applied', 5, 4)->nullable()->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('raffle_prizes', function (Blueprint $table) {
            $table->dropColumn('weight_applied');
        });
    }
};
