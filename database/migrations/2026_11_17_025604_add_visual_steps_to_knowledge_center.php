<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table): void {
            $table->json('workflow_steps')->nullable()->after('content');
        });

        Schema::create('knowledge_article_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_article_id')
                ->constrained('knowledge_articles')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('step_title', 200);
            $table->text('step_description')->nullable();
            $table->text('action_instruction')->nullable();
            $table->text('expected_result')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt', 200)->nullable();
            $table->string('image_caption', 255)->nullable();
            $table->json('annotations')->nullable();
            $table->timestamps();

            $table->index(['knowledge_article_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_article_steps');

        Schema::table('knowledge_articles', function (Blueprint $table): void {
            $table->dropColumn('workflow_steps');
        });
    }
};
