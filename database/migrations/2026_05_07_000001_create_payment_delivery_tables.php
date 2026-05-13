<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('provider', 40)->default('manual')->index();
            $table->string('type', 40)->default('branch')->index();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('base_price_cents')->default(0);
            $table->unsignedBigInteger('free_from_cents')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type', 40)->default('manual')->index();
            $table->text('description')->nullable();
            $table->decimal('fee_percent', 6, 2)->default(0);
            $table->unsignedBigInteger('fixed_fee_cents')->default(0);
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('delivery_tariffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('region')->nullable()->index();
            $table->string('city')->nullable()->index();
            $table->unsignedBigInteger('min_order_cents')->default(0);
            $table->unsignedBigInteger('max_order_cents')->nullable();
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->unsignedBigInteger('free_from_cents')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['delivery_method_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_tariffs');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('delivery_methods');
    }
};
