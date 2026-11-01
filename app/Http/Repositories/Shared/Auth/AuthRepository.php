<?php

namespace App\Http\Repositories\Shared\Auth;

use App\Interfaces\RepositoryInterface;
use App\Models\User;
use App\Models\UserOtp;
use App\Utils\ConfigUtils;

class AuthRepository implements RepositoryInterface
{
    public function create(array $data)
    {
        return User::create($data);
    }
    public function first(int $id, array $columns = ['*'])
    {
        return User::where('id', $id)->first($columns);
    }
    public function update(int $id, array $attributes = []) {}
    public function delete(int $id) {}
    public function getAll(array $columns = ['*']) {}

    public function getUserByPhoneNumber(string $phoneNumber, array $columns = ['*'])
    {
        return User::where('phone', $phoneNumber)->first($columns);
    }

    public function generateOtp($user): UserOtp
    {
        $userOtp = UserOtp::where('user_id', $user->id)->latest()->first();
        $now = now();

        if ($userOtp && $now->isBefore($userOtp->expire_at)) {
            return $userOtp;
        }

        return UserOtp::create([
            'user_id' => $user->id,
            'otp' => ConfigUtils::generateOtpRandomInt(),
            'expire_at' => ConfigUtils::getExpireAtOtpUser(),
        ]);
    }

    public function getLatestUserOtpByOtp($id, $otp): ?UserOtp
    {
        return UserOtp::where('user_id', $id)->where('otp', $otp)->latest()->first();
    }
}
