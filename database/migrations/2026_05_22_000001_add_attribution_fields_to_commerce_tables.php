<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->string('source', 40)->nullable()->after('status')->index();
            $table->string('channel', 60)->nullable()->after('source')->index();
            $table->json('click_ids')->nullable()->after('utm_term');
            $table->string('landing_page_url')->nullable()->after('click_ids');
            $table->string('referrer_url')->nullable()->after('landing_page_url');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('channel', 60)->nullable()->after('source')->index();
            $table->json('click_ids')->nullable()->after('utm_term');
            $table->json('attribution')->nullable()->after('click_ids');
        });

        DB::table('marketing_event_outbox')
            ->selectRaw('MIN(id) as keep_id, provider, event_name, event_id, transport, COUNT(*) as duplicates_count')
            ->whereNotNull('event_id')
            ->groupBy('provider', 'event_name', 'event_id', 'transport')
            ->having('duplicates_count', '>', 1)
            ->orderBy('keep_id')
            ->get()
            ->each(function (object $duplicate): void {
                DB::table('marketing_event_outbox')
                    ->where('provider', $duplicate->provider)
                    ->where('event_name', $duplicate->event_name)
                    ->where('event_id', $duplicate->event_id)
                    ->where('transport', $duplicate->transport)
                    ->where('id', '<>', $duplicate->keep_id)
                    ->delete();
            });

        Schema::table('marketing_event_outbox', function (Blueprint $table): void {
            $table->unique(
                ['provider', 'event_name', 'event_id', 'transport'],
                'marketing_outbox_event_transport_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('marketing_event_outbox', function (Blueprint $table): void {
            $table->dropUnique('marketing_outbox_event_transport_unique');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['channel', 'click_ids', 'attribution']);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropColumn(['source', 'channel', 'click_ids', 'landing_page_url', 'referrer_url']);
        });
    }
};
