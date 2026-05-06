<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name');
            $table->string('author_email')->nullable();
            $table->string('author_phone', 32)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('status', 24)->default('pending')->index();
            $table->boolean('is_verified_buyer')->default(false)->index();
            $table->string('source', 64)->default('site')->index();
            $table->text('moderation_note')->nullable();
            $table->text('admin_reply')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status', 'published_at'], 'reviews_product_status_published_idx');
            $table->index(['customer_id', 'created_at'], 'reviews_customer_created_idx');
            $table->index(['status', 'created_at'], 'reviews_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
