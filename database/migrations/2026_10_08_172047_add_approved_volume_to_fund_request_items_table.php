<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_request_items', function (Blueprint $table) {
            // Menambahkan kolom approved_volume (nullable, default null) setelah unit_price
            $table->decimal('approved_volume', 10, 2)->nullable()->default(null)->after('unit_price');
            $table->enum('verification_status', ['approved', 'rejected', 'partial'])
                ->nullable()
                ->default(null)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('fund_request_items', function (Blueprint $table) {
            $table->dropColumn('approved_volume');
            $table->enum('verification_status', ['approved', 'rejected', 'partial'])
                ->default('approved')
                ->change();
        });
    }
};
