<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_feed_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 32);
            $table->boolean('is_enabled')->default(false);
            $table->string('brand')->nullable();
            $table->string('google_product_category')->nullable();
            $table->string('custom_title')->nullable();
            $table->text('custom_description')->nullable();
            $table->string('google_gender', 16)->nullable();
            $table->string('google_age_group', 16)->nullable();
            $table->string('google_material', 200)->nullable();
            $table->string('google_pattern', 100)->nullable();
            $table->string('google_size_system', 16)->nullable();
            $table->json('google_size_types')->nullable();
            $table->boolean('google_is_bundle')->default(false);
            $table->string('google_item_group_id', 80)->nullable();
            $table->json('google_product_highlights')->nullable();
            $table->json('google_product_details')->nullable();
            $table->string('custom_label_0')->nullable();
            $table->string('custom_label_1')->nullable();
            $table->string('custom_label_2')->nullable();
            $table->string('custom_label_3')->nullable();
            $table->string('custom_label_4')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'channel']);
            $table->index(['channel', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_feed_configs');
    }
};
