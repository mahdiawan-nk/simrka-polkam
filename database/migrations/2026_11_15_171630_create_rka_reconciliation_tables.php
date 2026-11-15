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
        Schema::create('rka_adjustment_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_id')->constrained('rka_schedules')->cascadeOnDelete();
            $table->foreignId('source_adjustment_id')->constrained('rka_adjustments')->cascadeOnDelete();
            $table->foreignId('counterpart_adjustment_id')->nullable()->constrained('rka_adjustments')->nullOnDelete();
            $table->foreignId('source_item_id')->nullable()->constrained('rka_items')->nullOnDelete();
            $table->foreignId('counterpart_item_id')->nullable()->constrained('rka_items')->nullOnDelete();
            $table->foreignId('source_department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('target_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('reconciliation_status', 30);
            $table->string('matching_level', 30);
            $table->decimal('matched_amount', 15, 2)->nullable();
            $table->string('matching_rule', 100)->nullable();
            $table->json('match_details')->nullable();
            $table->foreignId('source_import_batch_id')->nullable()->constrained('rka_approved_import_batches')->nullOnDelete();
            $table->foreignId('counterpart_import_batch_id')->nullable()->constrained('rka_approved_import_batches')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->unique(['source_adjustment_id'], 'uq_rka_adj_match_source');
            $table->index(['schedule_id', 'reconciliation_status'], 'idx_reconcile_schedule_status');
            $table->index(['schedule_id', 'target_department_id', 'reconciliation_status'], 'idx_reconcile_waiting_queue');
            $table->index(['source_department_id', 'target_department_id'], 'idx_reconcile_depts');
        });

        Schema::create('rka_reconciliation_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rka_adjustment_match_id')->constrained('rka_adjustment_matches')->cascadeOnDelete();
            $table->string('action', 50);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 50);
            $table->text('reason')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamps();

            $table->index(['rka_adjustment_match_id', 'created_at'], 'idx_reconcile_history_match_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rka_reconciliation_histories');
        Schema::dropIfExists('rka_adjustment_matches');
    }
};
