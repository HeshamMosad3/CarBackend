<?php

namespace App\Enums;

/**
 * The things that need the admin to do something. Each one raises an alert in the dashboard
 * (browser notification + sound) and can be emailed to the recipients who chose it.
 */
enum AdminAlertType: string
{
    case ShippingReview = 'shipping_review';
    case ShippingPaid = 'shipping_paid';
    case ShippingRefund = 'shipping_refund';
    case ShippingLegacy = 'shipping_legacy';
    case VendorJoin = 'vendor_join';
    case ComplaintNew = 'complaint_new';

    public function label(): string
    {
        return match ($this) {
            self::ShippingReview => 'شحنة بانتظار المراجعة',
            self::ShippingPaid => 'زبون دفع رسوم الشحن',
            self::ShippingRefund => 'مبلغ يلزم رده للزبون',
            self::ShippingLegacy => 'طلب شحن بانتظار الحجز',
            self::VendorJoin => 'طلب انضمام شركة',
            self::ComplaintNew => 'شكوى جديدة',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ShippingReview => 'أكمل الزبون بيانات شحنة وتنتظر مراجعتك وتسعيرها',
            self::ShippingPaid => 'دفع الزبون رسوم الشحن وتنتظر منك تأكيد الشحن',
            self::ShippingRefund => 'شحنة مدفوعة أُلغيت أو تعذّر توفيرها ويلزمك رد المبلغ',
            self::ShippingLegacy => 'طلب شحن (بالنظام القديم) أكّده الزبون وينتظر الحجز',
            self::VendorJoin => 'شركة أو ورشة سجّلت وتنتظر القبول',
            self::ComplaintNew => 'وصلت شكوى جديدة من عميل أو شركة',
        };
    }

    /** Bootstrap colour name used for the badge and the notification. */
    public function tone(): string
    {
        return match ($this) {
            self::ShippingRefund => 'danger',
            self::ShippingReview, self::ShippingLegacy, self::ShippingPaid => 'warning',
            self::VendorJoin => 'primary',
            self::ComplaintNew => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ShippingReview, self::ShippingLegacy => 'bi-truck',
            self::ShippingPaid => 'bi-credit-card-2-front',
            self::ShippingRefund => 'bi-arrow-counterclockwise',
            self::VendorJoin => 'bi-building-add',
            self::ComplaintNew => 'bi-flag',
        };
    }

    /** Urgent alerts get the longer, more insistent sound. */
    public function urgent(): bool
    {
        return $this === self::ShippingRefund;
    }

    /** @return array<int, array{key:string,label:string,description:string,icon:string,tone:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => [
            'key' => $t->value,
            'label' => $t->label(),
            'description' => $t->description(),
            'icon' => $t->icon(),
            'tone' => $t->tone(),
        ], self::cases());
    }

    /** @return string[] */
    public static function keys(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
