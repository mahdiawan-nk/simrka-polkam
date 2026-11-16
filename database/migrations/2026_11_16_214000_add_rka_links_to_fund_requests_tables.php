<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('fund_requests', 'rka_header_id')) {
                $table->foreignId('rka_header_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('rka_headers')
                    ->nullOnDelete();
            }
        });

        Schema::table('fund_request_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('fund_request_items', 'rka_item_id')) {
                $table->foreignId('rka_item_id')
                    ->nullable()
                    ->after('fund_request_id')
                    ->constrained('rka_items')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('fund_request_items', 'rka_account_code_snapshot')) {
                $table->string('rka_account_code_snapshot', 50)->nullable()->after('account_id');
            }
            if (! Schema::hasColumn('fund_request_items', 'rka_account_name_snapshot')) {
                $table->string('rka_account_name_snapshot', 255)->nullable()->after('rka_account_code_snapshot');
            }
            if (! Schema::hasColumn('fund_request_items', 'rka_approved_volume_snapshot')) {
                $table->decimal('rka_approved_volume_snapshot', 12, 2)->nullable()->after('rka_account_name_snapshot');
            }
            if (! Schema::hasColumn('fund_request_items', 'rka_approved_total_snapshot')) {
                $table->decimal('rka_approved_total_snapshot', 15, 2)->nullable()->after('rka_approved_volume_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fund_request_items', function (Blueprint $table): void {
            if (Schema::hasColumn('fund_request_items', 'rka_item_id')) {
                $table->dropForeign(['rka_item_id']);
                $table->dropColumn('rka_item_id');
            }
            foreach ([
                'rka_account_code_snapshot',
                'rka_account_name_snapshot',
                'rka_approved_volume_snapshot',
                'rka_approved_total_snapshot',
            ] as $col) {
                if (Schema::hasColumn('fund_request_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('fund_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('fund_requests', 'rka_header_id')) {
                $table->dropForeign(['rka_header_id']);
                $table->dropColumn('rka_header_id');
            }
        });
    }
};
