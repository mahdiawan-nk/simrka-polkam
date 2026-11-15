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
        Schema::create('backup_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Identitas pengguna
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Aktivitas
            $table->string('action', 50);
            // backup_created, backup_downloaded,
            // backup_deleted, restore_uploaded,
            // restore_started, restore_completed,
            // restore_failed, restore_cancelled,
            // configuration_updated

            $table->string('subject_type', 50)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('status', 30)->default('success');
            // success, failed, rejected

            // Detail aktivitas
            $table->text('description')->nullable();

            // Data sebelum dan sesudah perubahan
            $table->json('properties')->nullable();

            // Informasi request
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Waktu aktivitas
            $table->timestamp('occurred_at');

            $table->timestamps();

            // Index
            $table->index('action');
            $table->index('status');
            $table->index('subject_type');
            $table->index('subject_id');
            $table->index('occurred_at');
            $table->index(['user_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_activity_logs');
    }
};
