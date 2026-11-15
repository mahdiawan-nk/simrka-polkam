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
        Schema::create('table_empty_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_empty_run_id')
                ->constrained('table_empty_runs')
                ->cascadeOnDelete();

            $table->string('table_name');

            $table->unsignedBigInteger('rows_before')->default(0);
            $table->unsignedBigInteger('rows_deleted')->default(0);
            $table->unsignedBigInteger('rows_after')->default(0);

            $table->string('status', 30)->default('pending');
            // pending, processing, success, failed, skipped

            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('table_name');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_empty_items');
    }
};
