<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rka_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rka_header_id')->constrained('rka_headers')->cascadeOnDelete();

            // Relasi ke tabel accounts (Level 1 / Kelompok Akun)
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();

            $table->text('program_name');
            $table->text('activity_name');
            $table->string('location', 150)->default('Politeknik Kampar');

            // Matriks Indikator
            $table->string('indicator_category', 50);
            $table->text('performance_benchmark');
            $table->string('target_value', 50);
            $table->string('unit_target', 50);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_indicators');
    }
};
