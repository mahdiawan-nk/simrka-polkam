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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Contoh: 5.3.1.04, 5.2.1.03
            $table->string('name'); // Contoh: Belanja Modal Peralatan dan Tools
            $table->text('description')->nullable(); // Contoh Penggunaan (dari Excel 2)
            $table->text('managed_by_note')->nullable(); // Catatan Pos Terpusat (teks merah dari Excel 2)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
