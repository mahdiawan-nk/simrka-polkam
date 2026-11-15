<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rka_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rka_header_id')->constrained('rka_headers')->cascadeOnDelete();
            $table->foreignId('rka_indicator_id')->constrained('rka_indicators')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('form_type', 10);
            $table->text('description');
            
            // Usulan Unit
            $table->decimal('proposed_volume', 12, 2)->default(0);
            $table->string('unit_symbol', 50);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('proposed_total', 15, 2)
                  ->storedAs('proposed_volume * unit_price');
            
            // Hasil Review
            $table->decimal('approved_volume', 12, 2)->nullable();
            $table->decimal('approved_total', 15, 2)->nullable();
            
            // Status & Penandaan Visual
            $table->string('status', 30)->default('PENDING');
            $table->boolean('is_trimmed')->default(false);
            $table->text('review_notes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_items');
    }
};