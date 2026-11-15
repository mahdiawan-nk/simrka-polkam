<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rka_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_name');
            $table->foreignId('director_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('director_name', 150);
            $table->string('director_nrp', 50);
            $table->string('foundation_head_name', 150);
            $table->string('signing_place', 100)->default('Bangkinang');

            $table->jsonb('pdf_header_config')->nullable(); // Menampung logo, kop surat, margin atas, dsb.
            $table->jsonb('pdf_footer_config')->nullable(); // Menampung catatan kaki, penomoran halaman, dsb.

            $table->jsonb('signing_config')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_settings');
    }
};
