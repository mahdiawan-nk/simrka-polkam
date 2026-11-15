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
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            // Identitas backup
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Jenis backup
            $table->string('type', 30);
            // full, table

            $table->string('format', 20);
            // sql, csv

            // Daftar tabel yang di-backup
            $table->json('table_names')->nullable();

            // Status proses
            $table->string('status', 30)->default('pending');
            // pending, processing, success, failed, cancelled

            // Informasi file
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('total_size')->default(0);

            // Informasi integritas
            $table->string('checksum_algorithm', 20)->default('sha256');

            // Informasi proses
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('duration_seconds')->nullable();

            // Informasi error
            $table->longText('error_message')->nullable();

            // Metadata tambahan
            $table->json('metadata')->nullable();

            // Pengguna
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Index
            $table->index('type');
            $table->index('format');
            $table->index('status');
            $table->index('created_at');
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
