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
        Schema::table('rka_adjustments', function (Blueprint $table) {
            $table->boolean('is_imported')->default(false)->after('adjusted_by');
            $table->foreignId('imported_rka_item_id')->nullable()->constrained('rka_items')->nullOnDelete()->after('is_imported');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rka_adjustments', function (Blueprint $table) {
            $table->dropForeign(['imported_rka_item_id']);
            $table->dropColumn(['is_imported', 'imported_rka_item_id']);
        });
    }
};
