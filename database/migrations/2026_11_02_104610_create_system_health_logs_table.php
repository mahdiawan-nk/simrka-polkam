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
        Schema::create('system_health_logs', function (Blueprint $table) {
            $table->id();
            // Identitas pemeriksaan
            $table->uuid('uuid')->unique();

            // Komponen yang diperiksa
            $table->string('component', 50);
            // database, storage, disk, application,
            // queue, scheduler, php, memory, backup

            $table->string('check_name', 100)->nullable();

            // Status
            $table->string('status', 30);
            // healthy, warning, critical, unknown

            // Hasil pemeriksaan
            $table->text('message')->nullable();

            // Data pengukuran
            $table->json('metrics')->nullable();

            // Informasi waktu
            $table->timestamp('checked_at');

            // Durasi pemeriksaan
            $table->unsignedInteger('duration_ms')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Index
            $table->index('component');
            $table->index('status');
            $table->index('checked_at');
            $table->index(['component', 'checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_health_logs');
    }
};
