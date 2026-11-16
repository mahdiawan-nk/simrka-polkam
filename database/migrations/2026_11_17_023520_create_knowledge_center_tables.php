<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 80)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('knowledge_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('knowledge_categories')
                ->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('featured')->default(false)->index();
            $table->unsignedSmallInteger('reading_time')->default(3);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('module_key', 80)->nullable()->index();
            $table->string('resource_fqcn', 200)->nullable();
            $table->string('doc_version', 20)->default('1.0');
            $table->timestamp('last_verified_at')->nullable();
            $table->string('maintainer_name', 120)->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'featured', 'sort_order']);
        });

        Schema::create('knowledge_article_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_article_id')
                ->constrained('knowledge_articles')
                ->cascadeOnDelete();
            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['knowledge_article_id', 'role_id']);
        });

        Schema::create('knowledge_article_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_article_id')
                ->constrained('knowledge_articles')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->boolean('is_helpful');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['knowledge_article_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_article_feedback');
        Schema::dropIfExists('knowledge_article_role');
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('knowledge_categories');
    }
};
