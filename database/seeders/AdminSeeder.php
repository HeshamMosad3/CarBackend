<?php

namespace Database\Seeders;

use App\Enums\StatusUserEnum;
use App\Enums\user\UserRoleEnum;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Admin::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'user_id' => 1,
                'password' => Hash::make('12345678'),
            ]
        );
        if (!$admin->hasRole(UserRoleEnum::Super_Admin->value)) {
            $admin->assignRole(UserRoleEnum::Super_Admin->value);
        }
        // $admin = User::Where('phone', '0502782857')->first();

        // if (empty($admin)) {
        //     $admin = User::create([
        //         'name'     => 'Super Admin',
        //         'phone'     => '0502782857',
        //         'status'     => StatusUserEnum::Active->value,
        //     ]);
        //     Admin::create([
        //         'user_id' => $admin->id,
        //         'email' => 'admin@gmail.com',
        //         'password' => Hash::make('12345678'),
        //     ]);
        //     $admin->assignRole(UserRoleEnum::Super_Admin->value);
        // }
    }
}
