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

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ensure user role exists & create/update Customer account
        $customer = User::updateOrCreate(
            ['phone' => '0512345678'],
            [
                'name' => 'عميل تجريبي',
                'status' => StatusUserEnum::Active->value,
            ]
        );
        if (!$customer->hasRole(UserRoleEnum::User->value)) {
            $customer->assignRole(UserRoleEnum::User->value);
        }

        // 2. Create Vendor (Company) account
        $vendorUser = User::updateOrCreate(
            ['phone' => '0599999999'],
            [
                'name' => 'شركة الأمل لقطع الغيار',
                'status' => StatusUserEnum::Active->value,
            ]
        );
        if (!$vendorUser->hasRole(UserRoleEnum::Vendor->value)) {
            $vendorUser->assignRole(UserRoleEnum::Vendor->value);
        }

        $vendor = Vendor::updateOrCreate(
            ['user_id' => $vendorUser->id],
            [
                'company_name_ar' => 'شركة الأمل لقطع الغيار',
                'company_name_en' => 'Al-Amal Auto Parts',
                'description' => 'متخصصون في جميع قطع غيار السيارات والصيانة',
                'commercial_record' => '1010101010',
                'date_expire_commercial_record' => now()->addYears(5)->toDateString(),
                'phone_contact' => '0599999999',
                'is_hide_phone_contact' => false,
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        // Assign to all available cities so company can respond in all cities
        $cities = City::all();
        foreach ($cities as $city) {
            VendorCity::firstOrCreate([
                'vendor_id' => $vendor->id,
                'city_id' => $city->id,
            ]);
        }

        // Assign to all available categories
        $categories = Category::all();
        foreach ($categories as $category) {
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
