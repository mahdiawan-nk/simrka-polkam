<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rka_unlock_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rka_header_id')->nullable()->constrained('rka_headers')->nullOnDelete();
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('requested_by')->constrained('users');
            $table->text('reason');
            $table->text('error_description')->nullable();
            $table->string('status', 30)->default('PENDING');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('reimport_granted_at')->nullable();
            $table->timestamp('reimport_expires_at')->nullable();
            $table->timestamp('reimport_used_at')->nullable();
            $table->unsignedBigInteger('reimport_batch_id')->nullable();
            $table->timestamps();

            $table->index(['rka_header_id', 'status'], 'idx_rka_unlock_header_status');
            $table->index(['department_id', 'status'], 'idx_rka_unlock_dept_status');
            $table->index(['requested_by', 'status'], 'idx_rka_unlock_user_status');
        });

        Schema::create('rka_approved_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rka_header_id')->nullable()->constrained('rka_headers')->nullOnDelete();
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('schedule_id')->constrained('rka_schedules');
            $table->foreignId('imported_by')->constrained('users');
            $table->foreignId('unlock_request_id')->nullable()->constrained('rka_unlock_requests')->nullOnDelete();
            $table->string('import_type', 30)->default('INITIAL');
            $table->string('original_filename', 255);
            $table->string('file_checksum', 64);
            $table->unsignedInteger('belanja_count')->default(0);
            $table->unsignedInteger('adjustment_count')->default(0);
            $table->decimal('total_proposed', 15, 2)->default(0);
            $table->decimal('total_approved', 15, 2)->default(0);
            $table->string('status', 30)->default('SUCCESS');
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['rka_header_id', 'created_at'], 'idx_rka_import_batch_header_created');
            $table->index(['department_id', 'schedule_id'], 'idx_rka_import_batch_dept_schedule');
        });

        Schema::table('rka_items', function (Blueprint $table): void {
            $table->string('source_ref_id', 64)->nullable()->after('id');
            $table->index('source_ref_id', 'idx_rka_items_source_ref');
        });

        Schema::table('rka_adjustments', function (Blueprint $table): void {
            $table->string('source_ref_id', 64)->nullable()->after('id');
            $table->index('source_ref_id', 'idx_rka_adjustments_source_ref');
        });
    }

    public function down(): void
    {
        Schema::table('rka_adjustments', function (Blueprint $table): void {
            $table->dropIndex('idx_rka_adjustments_source_ref');
            $table->dropColumn('source_ref_id');
        });

        Schema::table('rka_items', function (Blueprint $table): void {
            $table->dropIndex('idx_rka_items_source_ref');
            $table->dropColumn('source_ref_id');
        });

        Schema::dropIfExists('rka_approved_import_batches');
        Schema::dropIfExists('rka_unlock_requests');
    }
};
