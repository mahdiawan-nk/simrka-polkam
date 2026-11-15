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
        Schema::create('rka_item_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rka_item_id')->unique()->constrained('rka_items')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('approved_volume', 15, 2);
            $table->decimal('approved_unit_price', 15, 2);
            $table->decimal('approved_total', 15, 2);

            $table->string('status', 20)->default('PENDING');
            $table->boolean('is_trimmed')->default(false);
            $table->decimal('price_trimmed', 15, 2)->default(0.00);
            $table->text('review_notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rka_item_reviews');
    }
};
