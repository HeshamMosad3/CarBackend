<?php

namespace App\Utils;

class ConfigUtils
{
    public static function getExpireAtOtpUser()
    {
        return now()->addMinutes(5);
    }

    public static function generateOtpRandomInt(): int
    {
        // return random_int(10000, 99999);
        return 11111;
    }

    public static function getAmountRateAppForCharge()
    {
        return 10;
    }

    /** Days a customer has to pay the shipping cost before the request is canceled automatically. */
    public static function getShippingPaymentWindowDays(): int
    {
        return 3;
    }

    /** Hours after the cost is sent at which the customer gets a single payment reminder. */
    public static function getShippingPaymentReminderAfterHours(): int
    {
        return 24;
    }
}
