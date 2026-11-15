<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rka_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rka_header_id')->constrained('rka_headers')->cascadeOnDelete();
            // Relasi opsional ke indikator terkait (jika penyesuaian terkait dengan indikator tertentu)
            $table->foreignId('rka_indicator_id')->nullable()->constrained('rka_indicators')->nullOnDelete();
            // Relasi opsional ke item usulan asal (jika penyesuaian ditarik dari rka_items)
            $table->foreignId('rka_item_id')->nullable()->constrained('rka_items')->nullOnDelete();
            
            // Tipe Penyesuaian: 
            // 'A' = Usulan Dipindah / Tidak Dianggarkan di Unit Ini
            // 'B' = Pos Dikelola Unit Ini untuk Unit Lain (Shared Cost / Usulan Tambahan Reviewer)
            $table->char('section_type', 1);
            $table->string('section_name'); // Bagian A: Penyesuaian Unit Asal, Bagian B: Penyesuaian Unit Pengelola
            // Relasi opsional ke akun terkait (jika penyesuaian terkait dengan akun tertentu)
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->text('description');
            
            // Mapping Unit Terkait
            // For Section A: Unit asal (unit ini), Unit Tujuan (destination_unit_id)
            // For Section B: Unit Pengelola (unit ini), Unit Penerima Manfaat (target_unit_id)
            $table->foreignId('destination_unit_id')->nullable()->constrained('departments')->nullOnDelete(); 
            $table->foreignId('target_unit_id')->nullable()->constrained('departments')->nullOnDelete(); 

            // Nominal Usulan vs Hasil Review
            $table->decimal('proposed_amount', 15, 2)->default(0);
            $table->decimal('approved_amount', 15, 2)->default(0);
            
            // Status Aksi Reviewer
            // Contoh: 'MOVED' (Dipindah), 'REJECTED' (Dibatalkan/Tidak Dianggarkan), 'SHARED_COST' (Dialokasikan), 'ADDED_BY_REVIEWER'
            $table->string('action_type', 50)->default('MOVED');
            $table->text('notes')->nullable(); // Catatan Alasan Pemindahan / Penambahan
            
            // Siapa yang melakukan penyesuaian saat review
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_adjustments');
    }
};