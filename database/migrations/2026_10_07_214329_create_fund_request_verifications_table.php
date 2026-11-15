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
        Schema::create('fund_request_verifications', function (Blueprint $table) {
            $table->id();
            
            // Foreign key ke tabel fund_requests
            $table->foreignId('fund_request_id')
                ->constrained('fund_requests')
                ->cascadeOnDelete();

            // Akun asal (sebelum dikoreksi)
            $table->foreignId('account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();

            // Akun final (jika ada koreksi kode akun)
            $table->foreignId('final_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();

            // Sisa Pagu Sebelum Verifikasi
            $table->decimal('remaining_budget', 15, 2)->default(0);

            // Jumlah Nominal yang Disetujui (Rp)
            $table->decimal('amount_approved', 15, 2)->default(0);

            // Status Persetujuan per Kode (Disetujui / Ditolak / Dikoreksi)
            $table->string('status')->default('Disetujui');

            // Nomor Bukti Register Keuangan
            $table->string('register_proof_number')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_request_verifications');
    }
};