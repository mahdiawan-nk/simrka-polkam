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
        Schema::create('restore_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Referensi backup internal
            $table->foreignId('backup_run_id')
                ->nullable()
                ->constrained('backup_runs')
                ->nullOnDelete();

            $table->foreignId('backup_file_id')
                ->nullable()
                ->constrained('backup_files')
                ->nullOnDelete();

            // Identitas restore
            $table->string('name');

            // Sumber restore
            $table->string('source_type', 30);
            // internal, uploaded

            $table->string('source_format', 20)->default('sql');

            $table->string('source_file_name')->nullable();
            $table->text('source_file_path')->nullable();

            // Target restore
            $table->string('type', 30);
            // full, table

            $table->json('table_names')->nullable();

            // Status
            $table->string('status', 30)->default('pending');
            // pending, processing, success, failed, cancelled

            // Safety backup sebelum restore
            $table->boolean('safety_backup_created')->default(false);

            $table->text('safety_backup_path')->nullable();

            $table->foreignId('safety_backup_run_id')
                ->nullable()
                ->constrained('backup_runs')
                ->nullOnDelete();

            // Informasi proses
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('duration_seconds')->nullable();

            // Statistik
            $table->unsignedInteger('tables_processed')->default(0);
            $table->unsignedBigInteger('rows_affected')->nullable();

            // Informasi error
            $table->longText('error_message')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            // Pengguna
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Index
            $table->index('source_type');
            $table->index('type');
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
        Schema::dropIfExists('restore_runs');
    }
};
