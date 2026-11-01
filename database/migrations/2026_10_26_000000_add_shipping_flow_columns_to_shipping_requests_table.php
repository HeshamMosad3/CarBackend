<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_requests', function (Blueprint $table) {
            // Cost the admin negotiated manually with a carrier; this is the whole amount the customer pays.
            $table->double('shipping_cost')->nullable();
            $table->timestamp('cost_sent_at')->nullable();
            $table->timestamp('payment_due_at')->nullable();
            $table->timestamp('payment_reminded_at')->nullable();

            // Tracked separately from `status` so the shipping lifecycle and the money lifecycle can't be confused.
            $table->string('payment_status', 30)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_note')->nullable();

            $table->string('carrier_name')->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('pickup_details')->nullable();

            // Customer delivery point.
            $table->string('name_destination')->nullable();
            $table->double('lat_destination')->nullable();
            $table->double('lng_destination')->nullable();

            $table->text('admin_notes')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('status_changed_at')->nullable();

            // "{request_id}-{response_id}" while a new-flow shipment is open, NULL once it ends.
            // The unique index makes a second open shipment for the same response impossible
            // (NULLs don't collide, and this works on both MySQL and SQLite).
            $table->string('active_key', 40)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_requests', function (Blueprint $table) {
            $table->dropUnique(['active_key']);
            $table->dropColumn([
                'shipping_cost', 'cost_sent_at', 'payment_due_at', 'payment_reminded_at',
                'payment_status', 'paid_at', 'refunded_at', 'refund_note',
                'carrier_name', 'tracking_number', 'pickup_details',
                'name_destination', 'lat_destination', 'lng_destination',
                'admin_notes', 'cancel_reason', 'status_changed_at', 'active_key',
            ]);
        });
    }
};
