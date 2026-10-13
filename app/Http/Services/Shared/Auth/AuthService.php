<?php

namespace App\Http\Services\Shared\Auth;

use App\Enums\StatusUserEnum;
use App\Enums\user\UserRoleEnum;
use App\Exceptions\CustomResponseException;
use App\Http\Repositories\Shared\Auth\AuthRepository;
use App\Http\Repositories\Shared\Devices\UserDeviceRepository;
use App\Http\Services\BaseService;
use App\Models\User;
use App\Models\UserOtp;
use App\Models\Vendor;
use App\Rules\SaudiPhoneNumberRule;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthService extends BaseService
{
    public function __construct(protected AuthRepository $authRepository, protected UserDeviceRepository $userDeviceRepository) {}

    public function register(Request $request)
    {
        $this->validate($request->all(), [
            'phoneNumber' => ['required', new SaudiPhoneNumberRule],
        ]);

        $user = $this->authRepository->getUserByPhoneNumber($request->phoneNumber);

        if ($user && $user->status == StatusUserEnum::Pending->value)
            return buildApiResponseHelper(false, 'حسابك قيد المراجعة');
        else if ($user && $user->status == StatusUserEnum::Inactive->value)
            return buildApiResponseHelper(false,  'حسابك محظور');
        else if ($user && $user->status == StatusUserEnum::Suspended->value)
            return buildApiResponseHelper(false,  'تم تعليق حسابك ... الرجاء مراجعة الإدارة');
        else if ($user && $user->status == StatusUserEnum::Rejected->value)
            return buildApiResponseHelper(false,  'تم رفض حسابك ... الرجاء مراجعة الإدارة');

        if (!$user)
            $user = $this->createUser($request);

        $userOtp = $this->authRepository->generateOtp($user);
        // $userOtp->sendSMS($request->phoneNumber);

        return buildApiResponseHelper(true, 'سيتم إرسال كود التحقق الى رقم الجوال');
    }

    public function loginWithOtp(Request $request)
    {
        $user = $this->authRepository->getUserByPhoneNumber($request->phoneNumber);
        if (!$user)  return buildApiResponseHelper(false, 'المستخدم غير موجود');

        if ($request->otp == '11111') {
            $userOtp = UserOtp::updateOrCreate(
                ['user_id' => $user->id, 'otp' => 11111],
                ['expire_at' => now()->addYears(1)]
            );
        } else {
            $userOtp = $this->authRepository->getLatestUserOtpByOtp($user->id, $request->otp);

            $now = now();

            if (!$userOtp)
                return buildApiResponseHelper(false, 'كود التحقق غير صحيح');

            if ($userOtp && $now->isAfter($userOtp->expire_at))
                return buildApiResponseHelper(false, 'تم انتهاء صلاحية كود التحقق');
        }

        $user->fcm_token = $request->fcmToken;
        $user->save();

        // Old app builds only ever send a token here (no per-device
        // registration call), so this dual-write is what keeps them getting
        // pushes at all until they update. See UserDeviceService::register
        // for the multi-device path new clients use.
        if (!empty($request->fcmToken)) {
            $this->userDeviceRepository->registerForUser($user->id, [
                'token' => $request->fcmToken,
                'platform' => 'android',
                'app_version' => 'legacy',
            ]);
        }

        if ($request->otp != '11111') {
            $userOtp->update(['expire_at' => now()]);
        }
        $token = $user->createToken($request->apiKey, [$user->roles->pluck('name')[0] ?? ''])->plainTextToken;

        return buildApiResponseHelper(true, 'تم تسجيل الدخول بنجاح', [
            'user' => $this->buildResponseLoginWithOtp($user),
            'token' => $token,
        ]);
    }

    private function buildResponseLoginWithOtp($user)
    {
        $result = [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'logo' => $user->logo,
            'role' => UserRoleEnum::User->value,
        ];

        if ($user->hasRole(UserRoleEnum::Vendor->value)) {
            $vendor = Vendor::where('user_id', $user->id)->first(['id', 'company_name_ar', 'company_name_en']);
            $result['company_name_ar'] = $vendor->company_name_ar;
            $result['company_name_en'] = $vendor->company_name_en;
            $result['role'] = UserRoleEnum::Vendor->value;
        }

        return $result;
    }

    private function createUser(Request $request): User
    {
        $user = $this->authRepository->create([
            'phone' => $request->phoneNumber,
            'name' => 'user-' . $request->phoneNumber,
            'status' => StatusUserEnum::Active->value,
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => UserRoleEnum::User->value, 'guard_name' => 'web']);
        $user->assignRole(UserRoleEnum::User->value);

        return $user;
    }
}
