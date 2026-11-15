<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rka_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('fiscal_year', 10);
            $table->string('period_range', 100);
            $table->string('phase_name', 100);
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_late_submission')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_schedules');
    }
};
