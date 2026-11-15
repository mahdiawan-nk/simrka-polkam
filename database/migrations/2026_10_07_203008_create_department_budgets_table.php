<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('fiscal_year', 9); // Contoh: 2026/2027
            $table->decimal('allocated_amount', 15, 2)->default(0); // Pagu Anggaran Unit/Departemen
            $table->timestamps();

            // Memastikan 1 Department hanya punya 1 Pagu per Kode Akun dalam 1 Tahun Anggaran
            $table->unique(['department_id', 'account_id', 'fiscal_year'], 'dept_account_fiscal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_budgets');
    }
};
