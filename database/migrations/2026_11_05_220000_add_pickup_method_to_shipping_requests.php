<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_requests', function (Blueprint $table) {
            // How the parcel gets to the carrier: 'pickup' (the carrier collects it from the vendor)
            // or 'dropoff' (the vendor takes it to a carrier branch).
            $table->string('pickup_method', 20)->nullable()->after('pickup_details');
            // The OTO option the admin chose, and what that option supports (OTO's own value:
            // dropoffOnly, freePickup, freePickupDropoff...), so the form can offer only valid choices.
            $table->string('oto_delivery_option_id', 40)->nullable()->after('pickup_method');
            $table->string('carrier_pickup_support', 30)->nullable()->after('oto_delivery_option_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_requests', function (Blueprint $table) {
            $table->dropColumn(['pickup_method', 'oto_delivery_option_id', 'carrier_pickup_support']);
        });
    }
};
