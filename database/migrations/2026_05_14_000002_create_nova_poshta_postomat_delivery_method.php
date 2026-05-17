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

        $hasPostomat = DB::table('delivery_methods')
            ->where('provider', 'nova_poshta')
            ->where('type', 'postomat')
            ->exists();

        if ($hasPostomat) {
            return;
        }

        $branch = DB::table('delivery_methods')
            ->where('provider', 'nova_poshta')
            ->where('type', 'branch')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $branch) {
            return;
        }

        DB::table('delivery_methods')->insert([
            'name' => 'Нова пошта: поштомат',
            'code' => $this->uniqueCode('nova_poshta_postomat'),
            'provider' => 'nova_poshta',
            'type' => 'postomat',
            'description' => 'Отримання у поштоматі Нової пошти, якщо він доступний для обраного міста.',
            'base_price_cents' => (int) $branch->base_price_cents,
            'free_from_cents' => $branch->free_from_cents,
            'settings' => json_encode([], JSON_THROW_ON_ERROR),
            'is_active' => (bool) $branch->is_active,
            'sort_order' => (int) $branch->sort_order + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('delivery_methods')) {
            return;
        }

        DB::table('delivery_methods')
            ->where('provider', 'nova_poshta')
            ->where('type', 'postomat')
            ->where('code', 'like', 'nova_poshta_postomat%')
            ->delete();
    }

    private function uniqueCode(string $baseCode): string
    {
        $code = $baseCode;
        $suffix = 2;

        while (DB::table('delivery_methods')->where('code', $code)->exists()) {
            $code = $baseCode.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
};
