<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table): void {
            $table->text('secret_settings')->nullable()->after('settings');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_provider', 40)->nullable()->after('payment_method')->index();
            $table->string('payment_reference')->nullable()->after('payment_provider')->index();
            $table->timestamp('paid_at')->nullable()->after('payment_reference')->index();
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40)->index();
            $table->string('external_order_id')->nullable()->index();
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('payment_method')->nullable();
            $table->string('action', 40)->nullable();
            $table->string('status', 40)->default('pending')->index();
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->char('currency', 3)->default('UAH');
            $table->boolean('is_test')->default(false)->index();
            $table->json('request_payload')->nullable();
            $table->json('callback_payload')->nullable();
            $table->longText('raw_data')->nullable();
            $table->string('raw_signature')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();

            $table->index(['provider', 'status', 'created_at']);
            $table->index(['order_id', 'provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['payment_provider']);
            $table->dropIndex(['payment_reference']);
            $table->dropIndex(['paid_at']);
            $table->dropColumn([
                'payment_provider',
                'payment_reference',
                'paid_at',
            ]);
        });

        Schema::table('payment_methods', function (Blueprint $table): void {
            $table->dropColumn('secret_settings');
        });
    }
};
