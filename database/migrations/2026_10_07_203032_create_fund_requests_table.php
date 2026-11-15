<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_requests', function (Blueprint $table) {
            $table->id();
            
            // Header Form
            $table->string('document_number')->unique(); // Contoh: 013/PK.3/NOT/ICT/10.2026
            $table->date('request_date');
            $table->string('fiscal_year', 9); // Contoh: 2026/2027
            
            // A. KEGIATAN & DEPARTEMEN PEMOHON
            $table->foreignId('department_id')->constrained('departments');
            $table->string('activity_name'); // Nama Kegiatan / Keperluan
            $table->date('execution_date')->nullable(); // Waktu Pelaksanaan
            $table->string('responsible_person'); // Penanggung Jawab
            $table->string('disbursement_type'); // Jenis Pencairan (Panjar / Reimburse / dll)

            // D. PEMBAYARAN KEPADA
            $table->string('recipient_name');
            $table->string('bank_account_info')->nullable(); // Bank / No. Rekening

            // E. LAMPIRAN (Disimpan sebagai JSON array dari Filament CheckboxList)
            $table->json('attachments')->nullable(); // ['tor_kak', 'rab', 'sk_panitia', 'penawaran', 'daftar_hadir', 'foto']

            // Rekapitulasi Total & Status
            $table->decimal('total_requested', 15, 2)->default(0); // Total Dimohonkan
            $table->decimal('total_approved', 15, 2)->default(0); // Total Disetujui Keuangan
            
            $table->enum('status', [
                'draft', 
                'submitted', 
                'verified_finance', 
                'approved', 
                'rejected'
            ])->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_requests');
    }
};