<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rka_adjustments', function (Blueprint $table) {
            $table->decimal('proposed_volume', 12, 2)->default(0)->after('target_unit_id');
            $table->string('unit_symbol', 50)->after('proposed_volume');
            $table->decimal('unit_price', 15, 2)->default(0)->after('unit_symbol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rka_adjustments', function (Blueprint $table) {
            $table->dropColumn(['proposed_volume','unit_symbole','unit_price']);
        });
    }
};
