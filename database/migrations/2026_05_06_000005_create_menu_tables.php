<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('title');
            $table->string('type', 32)->default('custom_url');
            $table->nullableMorphs('linkable');
            $table->string('url')->nullable();
            $table->string('target', 16)->default('_self');
            $table->string('icon')->nullable();
            $table->string('badge')->nullable();
            $table->string('css_class')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'is_active', 'sort_order'], 'menu_items_tree_idx');
            $table->index(['type', 'linkable_type', 'linkable_id'], 'menu_items_linkable_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
