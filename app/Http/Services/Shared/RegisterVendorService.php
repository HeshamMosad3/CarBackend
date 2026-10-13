<?php

namespace App\Http\Services\Shared;

use App\Enums\StatusUserEnum;
use App\Enums\user\UserRoleEnum;
use App\Http\Repositories\Shared\Devices\UserDeviceRepository;
use App\Http\Repositories\Shared\RegisterVendorRepository;
use App\Utils\UploadUtils;
use Illuminate\Http\Request;

class RegisterVendorService
{
    public function __construct(
        protected RegisterVendorRepository $registerVendorRepository,
        protected UserDeviceRepository $userDeviceRepository,
    ) {}

    public function registerVendor(Request $request)
    {
        $createUser = $this->registerVendorRepository->createUser([
            'phone' => $request->phoneNumber,
            'name' => $request->companyNameAr,
            'fcm_token' => $request->fcmToken,
            'status' => StatusUserEnum::Pending->value,
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => UserRoleEnum::Vendor->value, 'guard_name' => 'web']);
        $createUser->assignRole(UserRoleEnum::Vendor->value);

        if (!empty($request->fcmToken)) {
            $this->userDeviceRepository->registerForUser($createUser->id, [
                'token' => $request->fcmToken,
                'platform' => 'android',
                'app_version' => 'legacy',
            ]);
        }

        $createVendor = $this->registerVendorRepository->createVendor([
            'user_id' => $createUser->id,
            'company_name_ar' => $request->companyNameAr,
            'commercial_record' => $request->commercialRecord,
            'national_id' => $request->commercialRecord,
            'date_expire_commercial_record' => $request->dateExpireCommercialRecord,
        ]);

        $this->registerVendorRepository->createVendorCities([
            'vendor_id' => $createVendor->id,
            'city_id' => $request->cityId,
        ]);

        foreach ($request->categoriesIds as $categoryId) {
            $this->registerVendorRepository->createVendorCategories([
                'vendor_id' => $createVendor->id,
                'category_id' => $categoryId,
            ]);
        }

        foreach ($request->images as $file) {
            $fileName = UploadUtils::encryptAndStoreSensitiveFile($file);
            $this->registerVendorRepository->createVendorDocuments([
                'vendor_id' => $createVendor->id,
                'document_type' => 'Commercial_Record',
                'file_path' => $fileName,
            ]);
        }
    }
}
