<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_filter_attributes', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->string('display_type', 24)->default('checkbox');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['category_id', 'attribute_id']);
            $table->index(['category_id', 'is_active', 'sort_order'], 'category_filter_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_filter_attributes');
    }
};
