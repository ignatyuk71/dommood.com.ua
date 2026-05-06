<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_color_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('size_charts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('code', 100)->unique();
            $table->text('description')->nullable();
            $table->json('content_json')->nullable();
            $table->longText('content_html')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('color_group_id')->nullable()->constrained('product_color_groups')->nullOnDelete();
            $table->foreignId('size_chart_id')->nullable()->constrained('size_charts')->nullOnDelete();
            $table->unsignedInteger('color_sort_order')->default(0);

            $table->index(['color_group_id', 'color_sort_order'], 'products_color_group_sort_idx');
            $table->index(['size_chart_id', 'status'], 'products_size_chart_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_color_group_sort_idx');
            $table->dropIndex('products_size_chart_status_idx');
            $table->dropConstrainedForeignId('color_group_id');
            $table->dropConstrainedForeignId('size_chart_id');
            $table->dropColumn('color_sort_order');
        });

        Schema::dropIfExists('size_charts');
        Schema::dropIfExists('product_color_groups');
    }
};
