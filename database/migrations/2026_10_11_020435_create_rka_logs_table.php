<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rka_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rka_header_id')->nullable()->constrained('rka_headers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action', 100);
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_logs');
    }
};