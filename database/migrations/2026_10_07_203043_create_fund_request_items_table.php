<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained('fund_requests')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts');
            
            // B. RINCIAN KEBUTUHAN
            $table->text('description'); // Uraian barang/jasa
            $table->decimal('volume', 10, 2); // Volume / Jumlah
            $table->string('unit', 50); // Satuan (Unit, Set, Pcs, dll)
            $table->decimal('unit_price', 15, 2); // Harga Satuan
            $table->decimal('total_price', 15, 2); // Total (volume * unit_price)

            // F. VERIFIKASI DAN PERSETUJUAN KEUANGAN
            $table->decimal('approved_price', 15, 2)->nullable(); // Jumlah Disetujui
            $table->enum('verification_status', ['approved', 'rejected', 'partial'])->default('approved');
            $table->text('finance_note')->nullable(); // Catatan Koreksi Keuangan

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_request_items');
    }
};