<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            DB::table('orders')
                ->whereIn('status', ['awaiting_confirmation', 'pending_payment'])
                ->update([
                    'status' => 'new',
                    'updated_at' => now(),
                ]);
        }

        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $checkoutSettings = SiteSetting::query()
            ->where('section', 'checkout')
            ->first();

        $payload = $checkoutSettings?->payload;

        if (! is_array($payload)) {
            return;
        }

        if (in_array($payload['default_order_status'] ?? null, ['awaiting_confirmation', 'pending_payment'], true)) {
            $payload['default_order_status'] = 'new';
            $checkoutSettings->update(['payload' => $payload]);
        }
    }

    public function down(): void
    {
        // Не відкатуємо дані замовлень назад у legacy-статуси.
    }
};
