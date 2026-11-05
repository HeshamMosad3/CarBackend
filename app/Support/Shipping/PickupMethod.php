<?php

namespace App\Support\Shipping;

/**
 * How a shipment reaches the carrier. OTO says, for every delivery option, what it supports
 * (`pickupDropoff`): dropoffOnly, freePickup, freePickupDropoff... The admin then picks one of the
 * methods that option allows, and the vendor is told exactly what to do.
 */
final class PickupMethod
{
    /** The carrier comes to the vendor's address and collects the parcel. */
    public const PICKUP = 'pickup';
    /** The vendor takes the parcel to a carrier branch. */
    public const DROPOFF = 'dropoff';

    public const ALL = [self::PICKUP, self::DROPOFF];

    /** @return string[] the methods an OTO option allows; unknown values allow both */
    public static function allowed(?string $support): array
    {
        return match (true) {
            $support === 'dropoffOnly' => [self::DROPOFF],
            in_array($support, ['freePickup', 'paidPickup', 'pickupOnly'], true) => [self::PICKUP],
            default => self::ALL,
        };
    }

    public static function label(?string $method): ?string
    {
        return match ($method) {
            self::PICKUP => 'استلام من الباب (المندوب يصل للتاجر)',
            self::DROPOFF => 'تسليم الشحنة بنفسه لفرع الشركة',
            default => null,
        };
    }

    /** What OTO's value means, in words (shown next to every option in the admin's list). */
    public static function supportLabel(?string $support): string
    {
        return match ($support) {
            'dropoffOnly' => 'تسليم للفرع فقط',
            'freePickup' => 'استلام من الباب',
            'paidPickup' => 'استلام من الباب (مدفوع)',
            'freePickupDropoff' => 'استلام من الباب أو تسليم للفرع',
            default => 'غير محدد',
        };
    }

    /** The text for "بيانات الاستلام", which the vendor reads. The admin can edit it before confirming. */
    public static function instructions(?string $method, ?string $carrier): string
    {
        $carrier = $carrier ?: 'شركة الشحن';

        return match ($method) {
            self::PICKUP => "سيصل مندوب {$carrier} لاستلام الشحنة من عنوانك. جهّز الطرد مغلّفاً وكن متاحاً للاستلام.",
            self::DROPOFF => "سلّم الشحنة بنفسك إلى أقرب فرع لـ {$carrier} واحتفظ بإيصال التسليم.",
            default => '',
        };
    }

    /** One short sentence for the notification sent when shipping is confirmed. */
    public static function notice(?string $method): string
    {
        return match ($method) {
            self::PICKUP => 'سيصل مندوب الشركة لاستلام الشحنة من التاجر',
            self::DROPOFF => 'على التاجر تسليم الشحنة إلى فرع الشركة',
            default => '',
        };
    }
}
