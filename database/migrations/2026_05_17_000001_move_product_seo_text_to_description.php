<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->whereRaw("(description IS NULL OR TRIM(description) = '')")
            ->whereRaw("seo_text IS NOT NULL AND TRIM(seo_text) <> ''")
            ->update([
                'description' => DB::raw('seo_text'),
                'seo_text' => null,
            ]);
    }

    public function down(): void
    {
    }
};
