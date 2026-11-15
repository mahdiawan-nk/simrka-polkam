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
        Schema::create('user_departments', function (Blueprint $table) {
            $table->id();
            // Foreign key ke tabel users
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Foreign key ke tabel departments
            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnDelete();

            // Tipe penugasan user di department (utama / plt)
            $table->enum('assignment_type', ['primary', 'plt'])->default('primary');

            // Tanggal berlaku status PLT (opsional)
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamps();

            // Mencegah duplikasi entri user dan department yang sama dengan tipe penugasan yang sama
            $table->unique(['user_id', 'department_id', 'assignment_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_departments');
    }
};
