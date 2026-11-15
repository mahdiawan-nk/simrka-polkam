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
        Schema::create('table_empty_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('name');
            $table->string('status', 30)->default('pending');
            // pending, processing, success, partial, failed, cancelled

            $table->unsignedInteger('total_tables')->default(0);
            $table->unsignedInteger('processed_tables')->default(0);
            $table->unsignedBigInteger('total_rows_deleted')->default(0);

            $table->boolean('backup_before_empty')->default(true);

            $table->foreignId('backup_run_id')
                ->nullable()
                ->constrained('backup_runs')
                ->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('duration_seconds')->nullable();

            $table->longText('error_message')->nullable();
            $table->json('metadata')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_empty_runs');
    }
};
