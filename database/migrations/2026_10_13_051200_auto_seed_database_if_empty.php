<?php

use App\Enums\StatusUserEnum;
use App\Enums\user\UserRoleEnum;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCity;
use App\Models\VendorSpecialty;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Run seeders if categories table is empty
        if (Category::count() === 0) {
            Artisan::call('db:seed', ['--force' => true]);
        }

        // 2. Ensure roles exist
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

        // 3. Customer account
        $customer = User::updateOrCreate(
            ['phone' => '0512345678'],
            [
                'name' => 'عميل تجريبي',
                'status' => StatusUserEnum::Active->value,
            ]
        );
        $customer->syncRoles(['user']);

        // 4. Vendor account
        $vendorUser = User::updateOrCreate(
            ['phone' => '0555555555'],
            [
                'name' => 'شركة الأمل لقطع الغيار',
                'status' => StatusUserEnum::Active->value,
            ]
        );
        $vendorUser->syncRoles(['vendor']);

        $vendor = Vendor::updateOrCreate(
            ['user_id' => $vendorUser->id],
            [
                'company_name_ar' => 'شركة الأمل لقطع الغيار',
                'company_name_en' => 'Al-Amal Auto Parts',
                'description' => 'متخصصون في قطع الغيار والصيانة',
                'commercial_record' => '1010101010',
                'date_expire_commercial_record' => now()->addYears(5)->toDateString(),
                'phone_contact' => '0555555555',
                'is_hide_phone_contact' => false,
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        // Link all cities to vendor
        foreach (City::all() as $city) {
            VendorCity::firstOrCreate([
                'vendor_id' => $vendor->id,
                'city_id' => $city->id,
            ]);
        }

        // Link all categories to vendor
        foreach (Category::all() as $category) {
            VendorSpecialty::firstOrCreate([
                'vendor_id' => $vendor->id,
                'category_id' => $category->id,
                'is_receive_all_brand_cars' => true,
            ]);
        }
    }

    public function down(): void
    {
    }
};
