<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // Pickup point for shipments, filled in the first time the vendor sends a shipping request.
            $table->string('store_name')->nullable();
            $table->string('store_city', 100)->nullable();
            $table->string('store_address')->nullable();
            $table->double('store_lat')->nullable();
            $table->double('store_lng')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['store_name', 'store_city', 'store_address', 'store_lat', 'store_lng']);
        });
    }
};
