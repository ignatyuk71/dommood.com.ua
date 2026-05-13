<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_integrations', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32)->unique();
            $table->string('status', 24)->default('disabled')->index();
            $table->string('mode', 16)->default('prod');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketing_integration_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_integration_id');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique('marketing_integration_id', 'marketing_settings_integration_unique');
            $table->foreign('marketing_integration_id', 'marketing_settings_fk')
                ->references('id')
                ->on('marketing_integrations')
                ->cascadeOnDelete();
        });

        Schema::create('marketing_integration_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_integration_id');
            $table->string('secret_type', 80);
            $table->text('secret_value')->nullable();
            $table->string('secret_last_four', 16)->nullable();
            $table->timestamp('last_rotated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['marketing_integration_id', 'secret_type'], 'marketing_credentials_unique');
            $table->foreign('marketing_integration_id', 'marketing_credentials_fk')
                ->references('id')
                ->on('marketing_integrations')
                ->cascadeOnDelete();
        });

        Schema::create('marketing_event_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_integration_id')->nullable();
            $table->string('provider', 32)->index();
            $table->string('event_name', 80);
            $table->string('event_id', 140)->nullable()->index();
            $table->string('transport', 24)->default('server')->index();
            $table->json('payload')->nullable();
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status', 'created_at'], 'marketing_outbox_provider_status_date_idx');
            $table->foreign('marketing_integration_id', 'marketing_outbox_fk')
                ->references('id')
                ->on('marketing_integrations')
                ->nullOnDelete();
        });

        Schema::create('marketing_integration_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_integration_id')->nullable();
            $table->string('action', 80);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('marketing_integration_id', 'marketing_audit_fk')
                ->references('id')
                ->on('marketing_integrations')
                ->nullOnDelete();
        });

        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_name', 80)->index();
            $table->string('event_id', 140)->nullable()->index();
            $table->string('source', 40)->nullable()->index();
            $table->string('channel', 60)->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->char('currency', 3)->default('UAH');
            $table->unsignedBigInteger('value_cents')->nullable();
            $table->json('utm')->nullable();
            $table->json('click_ids')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['source', 'event_name', 'occurred_at'], 'analytics_source_event_date_idx');
            $table->index(['order_id', 'event_name'], 'analytics_order_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('marketing_integration_audit_logs');
        Schema::dropIfExists('marketing_event_outbox');
        Schema::dropIfExists('marketing_integration_credentials');
        Schema::dropIfExists('marketing_integration_settings');
        Schema::dropIfExists('marketing_integrations');
    }
};
