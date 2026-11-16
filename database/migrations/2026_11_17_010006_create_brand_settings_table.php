<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('brand_name', 100)->default('e-RKA');
            $table->string('app_full_name', 150)->default('Sistem Perencanaan & Anggaran');
            $table->string('institution_name', 150)->default('Politeknik Kampar');
            $table->string('institution_short_name', 50)->default('SIMRKA');
            $table->string('tagline', 200)->default('Sistem Perencanaan dan Anggaran');
            $table->text('short_description')->nullable();
            $table->string('browser_title', 160)->default('SIMRKA / e-RKA — Politeknik Kampar');

            $table->string('logo_path')->nullable();
            $table->string('logo_light_path')->nullable();
            $table->string('logo_dark_path')->nullable();
            $table->string('logo_compact_path')->nullable();
            $table->string('login_logo_path')->nullable();
            $table->string('favicon_path')->nullable();

            $table->string('document_institution_name', 150)->nullable();
            $table->string('document_logo_path')->nullable();
            $table->string('document_tagline', 200)->nullable();
            $table->string('document_footer_text', 255)->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_settings');
    }
};
