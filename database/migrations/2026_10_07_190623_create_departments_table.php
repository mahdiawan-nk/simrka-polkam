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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();
            $table->string('type');
            $table->string('code')->unique(); // Contoh: ICT, PRODI-TI, YAYASAN
            $table->string('level_code', 10)->default('PK.3');
            $table->string('name'); // Contoh: ICT Center Politeknik Kampar
            $table->timestamps();
            $table->index('level_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
