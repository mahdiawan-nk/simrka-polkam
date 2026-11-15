<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rka_logs', function (Blueprint $table): void {
            $table->string('subject_type', 120)->nullable()->after('rka_header_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->string('description', 500)->nullable()->after('action');

            $table->index(['subject_type', 'subject_id'], 'idx_rka_logs_subject');
            $table->index(['rka_header_id', 'created_at'], 'idx_rka_logs_header_created');
            $table->index(['rka_header_id', 'action'], 'idx_rka_logs_header_action');
        });
    }

    public function down(): void
    {
        Schema::table('rka_logs', function (Blueprint $table): void {
            $table->dropIndex('idx_rka_logs_header_action');
            $table->dropIndex('idx_rka_logs_header_created');
            $table->dropIndex('idx_rka_logs_subject');

            $table->dropColumn(['subject_type', 'subject_id', 'description']);
        });
    }
};
