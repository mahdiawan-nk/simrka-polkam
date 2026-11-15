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
        Schema::create('fund_request_approvals', function (Blueprint $table) {
            $table->id();

            // Relasi ke pengajuan dana
            $table->foreignId('fund_request_id')
                ->constrained('fund_requests')
                ->cascadeOnDelete();

            // Tahapan Verifikasi: kabakku / wadir_bakku / director
            $table->enum('stage', [
                'kabakku',
                'wadir_bakku',
                'director',
            ]);

            // Keputusan: approved / rejected / revised
            $table->enum('action', [
                'approved',
                'rejected',
                'revised',
            ]);

            // Nominal yang disetujui pada tahapan ini
            $table->decimal('approved_amount', 15, 2)->nullable();

            // Catatan verifikasi / Alasan penolakan
            $table->text('note')->nullable();

            // User yang melakukan verifikasi
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_request_approvals');
    }
};