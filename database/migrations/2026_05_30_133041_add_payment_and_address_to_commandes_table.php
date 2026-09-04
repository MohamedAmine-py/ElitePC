<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->enum('payment_method', ['credit_card', 'paypal', 'cash_on_delivery'])->default('credit_card');
            $table->string('delivery_address')->nullable();
            $table->string('delivery_phone')->nullable();
            $table->date('estimated_delivery_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'delivery_address', 'delivery_phone', 'estimated_delivery_date']);
        });
    }
};
