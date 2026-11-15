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
        Schema::create('rka_generate_configs', function (Blueprint $table) {
            $table->id();
            // Relasi opsional ke rka_settings
            $table->foreignId('rka_setting_id')->nullable()->constrained('rka_settings')->nullOnDelete();

            $table->string('config_name', 150)->default('Konfigurasi Default Generate');
            $table->boolean('is_active')->default(true);

            // Array ID Departemen yang dipilih
            $table->jsonb('selected_department_ids');

            // Map data template indikator level 1
            $table->jsonb('template_indicators');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rka_generate_configs');
    }
};
