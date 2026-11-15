<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rka_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rka_header_id')->constrained('rka_headers')->cascadeOnDelete();
            $table->foreignId('approver_user_id')->constrained('users');
            $table->string('stage', 50);
            $table->string('action', 30);
            $table->text('comments')->nullable();
            $table->string('digital_signature_hash', 255)->nullable();
            $table->timestamp('signed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_approvals');
    }
};
