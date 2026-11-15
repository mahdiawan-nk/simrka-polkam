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
        Schema::table('accounts', function (Blueprint $table) {
            // 1. Kode tanpa titik untuk parser & pencarian cepat (misal: '52101')
            $table->string('code_clean')->nullable()->after('code')->index();

            // 2. Relasi Self-Referencing Parent ID (Tree Structure)
            $table->foreignId('parent_id')
                ->nullable()
                ->after('code_clean')
                ->constrained('accounts')
                ->nullOnDelete();

            $table->string('parent_code')->nullable()->after('parent_id')->index();

            // 3. Tingkat Hirarki (1: Kelompok, 2: Jenis, 3: Rincian)
            $table->unsignedTinyInteger('level')->default(3)->after('parent_code');
            $table->string('level_label', 30)->default('Rincian')->after('level'); // 'Kelompok', 'Jenis', 'Rincian'

            // 4. Flag pendukung kontrol UI & Pos Terpusat (Diletakkan setelah managed_by_note)
            $table->boolean('is_centralized')->default(false)->after('managed_by_note');
            $table->boolean('is_active')->default(true)->after('is_centralized');

            // 5. Index Komposit untuk optimasi Query Filtering & Leveling di Filament / Select Option
            $table->index(['level', 'is_active'], 'idx_accounts_level_active');
            $table->index(['is_centralized', 'is_active'], 'idx_accounts_centralized_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Drop Index Komposit terlebih dahulu
            $table->dropIndex('idx_accounts_level_active');
            $table->dropIndex('idx_accounts_centralized_active');

            // Drop Foreign Key & Index bawaan kolom
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['code_clean']);
            $table->dropIndex(['parent_code']);

            // Drop Kolom
            $table->dropColumn([
                'code_clean',
                'parent_id',
                'parent_code',
                'level',
                'level_label',
                'is_centralized',
                'is_active',
            ]);
        });
    }
};
