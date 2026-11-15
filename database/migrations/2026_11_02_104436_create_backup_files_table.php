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
        Schema::create('backup_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_run_id')
                ->constrained('backup_runs')
                ->cascadeOnDelete();

            // Identitas file
            $table->uuid('uuid')->unique();
            $table->string('file_name');

            // Lokasi penyimpanan
            $table->string('disk', 50)->default('local');
            $table->text('file_path');

            // Format file
            $table->string('format', 20);
            // sql, csv

            // Tabel yang terdapat dalam file
            $table->string('table_name')->nullable();

            // Informasi file
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedBigInteger('row_count')->nullable();

            // Integritas file
            $table->string('checksum', 128)->nullable();
            $table->string('checksum_algorithm', 20)->default('sha256');

            // Status file
            $table->string('status', 30)->default('success');
            // processing, success, failed

            $table->text('error_message')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Index
            $table->index('format');
            $table->index('table_name');
            $table->index('status');
            $table->index(['backup_run_id', 'format']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_files');
    }
};
