<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_requests', function (Blueprint $table) {
            // File name (not a URL) of the carrier's waybill image/PDF, kept on the private disk.
            $table->string('waybill_file')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_requests', function (Blueprint $table) {
            $table->dropColumn('waybill_file');
        });
    }
};
