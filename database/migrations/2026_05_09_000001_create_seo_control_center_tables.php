<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('section', 80)->index();
            $table->string('key', 120);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['section', 'key']);
        });

        Schema::create('seo_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 40)->index();
            $table->string('field', 40)->index();
            $table->text('template');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['entity_type', 'field']);
        });

        Schema::create('seo_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('source_path')->unique();
            $table->string('target_url');
            $table->unsignedSmallInteger('status_code')->default(301)->index();
            $table->boolean('preserve_query')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'status_code']);
        });

        Schema::create('seo_indexing_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('pattern');
            $table->string('pattern_type', 24)->default('prefix')->index();
            $table->string('robots_directive', 24)->nullable()->index();
            $table->string('meta_robots', 120)->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['pattern_type', 'is_active', 'sort_order'], 'seo_indexing_rule_lookup_idx');
        });

        Schema::create('sitemap_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 32)->default('completed')->index();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('product_urls_count')->default(0);
            $table->unsignedInteger('category_urls_count')->default(0);
            $table->unsignedInteger('page_urls_count')->default(0);
            $table->unsignedInteger('total_urls_count')->default(0);
            $table->string('file_path')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('filter_seo_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->json('filters')->nullable();
            $table->string('h1')->nullable();
            $table->string('title')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('canonical_url')->nullable();
            $table->longText('seo_text')->nullable();
            $table->boolean('is_indexable')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'is_indexable', 'is_active'], 'filter_seo_category_index_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filter_seo_pages');
        Schema::dropIfExists('sitemap_runs');
        Schema::dropIfExists('seo_indexing_rules');
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('seo_templates');
        Schema::dropIfExists('seo_settings');
    }
};
