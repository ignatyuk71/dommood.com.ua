<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('delivery_provider', 40)->nullable()->after('delivery_method')->index();
            $table->string('delivery_type', 40)->nullable()->after('delivery_provider')->index();
            $table->string('delivery_city_ref', 100)->nullable()->after('delivery_city');
            $table->string('delivery_branch_ref', 100)->nullable()->after('delivery_branch');
            $table->json('delivery_snapshot')->nullable()->after('delivery_recipient_phone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['delivery_provider']);
            $table->dropIndex(['delivery_type']);
            $table->dropColumn([
                'delivery_provider',
                'delivery_type',
                'delivery_city_ref',
                'delivery_branch_ref',
                'delivery_snapshot',
            ]);
        });
    }
};
