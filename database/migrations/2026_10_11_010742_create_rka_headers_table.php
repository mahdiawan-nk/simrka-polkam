<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rka_headers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('rka_schedules')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('setting_id')->constrained('rka_settings');
            
            // Bagian A: Pengusul
            $table->foreignId('unit_head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            
            // Bagian B: Keuangan & Pagu Unit
            $table->decimal('pagu_budget', 15, 2)->default(0.00);
            
            // Bagian C: Reviewer
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('review_date')->nullable();
            $table->text('general_notes')->nullable();
            
            // Status & Kontrol Portal
            $table->string('status', 30)->default('DRAFT');
            $table->boolean('is_locked')->default(false);
            $table->boolean('unlocked_by_admin')->default(false);
            
            $table->timestamps();

            $table->unique(['schedule_id', 'department_id'], 'unique_schedule_department');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_headers');
    }
};