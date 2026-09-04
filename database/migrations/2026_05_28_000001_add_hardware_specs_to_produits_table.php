<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Adds PC-hardware specification columns to the produits table.
 * These columns are nullable so existing/non-spec products are not affected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->string('brand', 100)->nullable()->after('image');
            $table->string('processor', 200)->nullable()->after('brand');
            $table->string('graphics_card', 200)->nullable()->after('processor');
            $table->string('ram_details', 200)->nullable()->after('graphics_card');
            $table->string('storage_details', 200)->nullable()->after('ram_details');
            $table->boolean('is_custom_build')->default(false)->after('storage_details');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn([
                'brand',
                'processor',
                'graphics_card',
                'ram_details',
                'storage_details',
                'is_custom_build',
            ]);
        });
    }
};
