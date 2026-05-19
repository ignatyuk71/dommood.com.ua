<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_methods')) {
            return;
        }

        $now = now();
        $sourceUrl = 'https://novaposhta.ua/send/from-branch/';
        $sourceLabel = 'Тарифи Нової пошти, дійсні з 13.04.2026';

        foreach ($this->methods($sourceUrl, $sourceLabel) as $method) {
            $exists = DB::table('delivery_methods')
                ->where('code', $method['code'])
                ->exists();

            DB::table('delivery_methods')->updateOrInsert(
                ['code' => $method['code']],
                [
                    ...$method,
                    'updated_at' => $now,
                    ...($exists ? [] : ['created_at' => $now]),
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('delivery_methods')) {
            return;
        }

        DB::table('delivery_methods')
            ->whereIn('code', [
                'nova_poshta_branch',
                'nova_poshta_postomat',
                'nova_poshta_courier',
            ])
            ->delete();
    }

    private function methods(string $sourceUrl, string $sourceLabel): array
    {
        return [
            [
                'name' => 'Нова пошта: відділення',
                'code' => 'nova_poshta_branch',
                'provider' => 'nova_poshta',
                'type' => 'branch',
                'description' => 'Отримання у відділенні Нової пошти. Орієнтир для легкої посилки: 90 грн по Україні.',
                'base_price_cents' => 9000,
                'free_from_cents' => null,
                'settings' => $this->settingsJson($sourceUrl, $sourceLabel, 'Мала посилка до 2 кг', '90 грн'),
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Нова пошта: поштомат',
                'code' => 'nova_poshta_postomat',
                'provider' => 'nova_poshta',
                'type' => 'postomat',
                'description' => 'Отримання у поштоматі Нової пошти. Базовий тариф 90 грн + 10 грн за поштомат.',
                'base_price_cents' => 10000,
                'free_from_cents' => null,
                'settings' => $this->settingsJson($sourceUrl, $sourceLabel, 'Мала посилка до 2 кг + поштомат', '100 грн'),
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'Нова пошта: курʼєрська доставка',
                'code' => 'nova_poshta_courier',
                'provider' => 'nova_poshta',
                'type' => 'courier',
                'description' => 'Доставка курʼєром Нової пошти за адресою клієнта. Орієнтир для легкої посилки: 150 грн по Україні.',
                'base_price_cents' => 15000,
                'free_from_cents' => null,
                'settings' => $this->settingsJson($sourceUrl, $sourceLabel, 'Мала посилка до 2 кг курʼєром', '150 грн'),
                'is_active' => true,
                'sort_order' => 30,
            ],
        ];
    }

    private function settingsJson(string $sourceUrl, string $sourceLabel, string $weightCategory, string $tariff): string
    {
        return json_encode([
            'tariff_source_url' => $sourceUrl,
            'tariff_source_label' => $sourceLabel,
            'tariff_weight_category' => $weightCategory,
            'tariff_reference' => $tariff,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
};
