<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore([
            ['name' => 'Super-Admin', 'guard_name' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin', 'guard_name' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'vendor', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'user', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
    }
};
