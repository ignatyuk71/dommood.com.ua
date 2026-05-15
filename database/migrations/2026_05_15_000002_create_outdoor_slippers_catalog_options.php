<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CODE = 'tapochky_dlia_vulytsi';
    private const TITLE = 'Тапочки для вулиці';

    public function up(): void
    {
        $now = now();

        $this->upsertColorGroup($now);
        $this->upsertSizeChart($now);
    }

    public function down(): void
    {
        DB::table('product_color_groups')
            ->where('code', self::CODE)
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('products')
                    ->whereColumn('products.color_group_id', 'product_color_groups.id');
            })
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('size_charts')
            ->where('code', self::CODE)
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('products')
                    ->whereColumn('products.size_chart_id', 'size_charts.id');
            })
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function upsertColorGroup($now): void
    {
        $payload = [
            'name' => self::TITLE,
            'description' => 'Група кольорів для моделей тапочок для вулиці.',
            'is_active' => true,
            'sort_order' => 0,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        $existing = DB::table('product_color_groups')
            ->where('code', self::CODE)
            ->first();

        if ($existing) {
            DB::table('product_color_groups')
                ->where('id', $existing->id)
                ->update($payload);

            return;
        }

        DB::table('product_color_groups')->insert([
            ...$payload,
            'code' => self::CODE,
            'created_at' => $now,
        ]);
    }

    private function upsertSizeChart($now): void
    {
        $contentJson = [
            'columns' => ['Розмір', 'Довжина стопи, см'],
            'rows' => [
                ['36/37', '24 см'],
                ['38/39', '25 см'],
                ['40/41', '26 см'],
                ['42/43', '27 см'],
            ],
        ];

        $contentHtml = <<<'HTML'
<table class="product-size-table">
    <thead>
        <tr>
            <th scope="col">Розмір</th>
            <th scope="col">Довжина стопи</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>36/37</td><td>24 см</td></tr>
        <tr><td>38/39</td><td>25 см</td></tr>
        <tr><td>40/41</td><td>26 см</td></tr>
        <tr><td>42/43</td><td>27 см</td></tr>
    </tbody>
</table>
HTML;

        $payload = [
            'title' => self::TITLE,
            'description' => 'Розмірна сітка для тапочок для вулиці. Виміряйте стопу від пʼяти до найдовшого пальця і порівняйте з таблицею.',
            'content_json' => json_encode($contentJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content_html' => $contentHtml,
            'image_path' => null,
            'is_active' => true,
            'sort_order' => 0,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        $existing = DB::table('size_charts')
            ->where('code', self::CODE)
            ->first();

        if ($existing) {
            DB::table('size_charts')
                ->where('id', $existing->id)
                ->update($payload);

            return;
        }

        DB::table('size_charts')->insert([
            ...$payload,
            'code' => self::CODE,
            'created_at' => $now,
        ]);
    }
};
