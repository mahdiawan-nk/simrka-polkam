<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rka_indicators', function (Blueprint $table) {
            // Drop kolom matriks lama
            $table->dropColumn([
                'indicator_category',
                'performance_benchmark',
                'target_value',
                'unit_target',
            ]);

            // Tambahkan kolom JSON/JSONB untuk menampung array matriks indikator
            $table->jsonb('indicator_matrix')->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('rka_indicators', function (Blueprint $table) {
            $table->dropColumn('indicator_matrix');

            // Revert ke kolom lama
            $table->string('indicator_category', 50)->after('location');
            $table->text('performance_benchmark');
            $table->string('target_value', 50);
            $table->string('unit_target', 50);
        });
    }
};
