<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('fund_requests', function (Blueprint $table) {
            // Jika menggunakan MySQL, ubah definisi ENUM agar mendukung status baru
            // Status: draft, submitted, verified_kabakku, verified_wadir_bakku, approved, rejected
            DB::statement("ALTER TABLE fund_requests MODIFY COLUMN status ENUM('draft', 'submitted', 'verified_kabakku', 'verified_wadir_bakku', 'approved', 'rejected') DEFAULT 'draft'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('fund_requests', function (Blueprint $table) {
            DB::statement("ALTER TABLE fund_requests MODIFY COLUMN status ENUM('draft', 'submitted', 'verified_finance', 'approved', 'rejected') DEFAULT 'draft'");
        });
    }
};
