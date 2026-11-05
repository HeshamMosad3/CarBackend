<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Things that need the admin to act (a shipment to review, a vendor to approve...). Kept so a
        // browser that was closed when one happened can still show it, and for the bell's history.
        Schema::create('admin_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url')->nullable();
            $table->string('tone', 20)->default('warning');
            $table->json('data')->nullable();
            $table->timestamps();
        });

        // Per-recipient control over the alert emails.
        Schema::table('admin_notification_emails', function (Blueprint $table) {
            $table->string('label')->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('label');
            // Alert types this address receives. NULL means all of them (how existing rows behave).
            $table->json('events')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('admin_notification_emails', function (Blueprint $table) {
            $table->dropColumn(['label', 'is_active', 'events']);
        });

        Schema::dropIfExists('admin_alerts');
    }
};
