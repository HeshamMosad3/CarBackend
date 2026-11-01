<?php

namespace App\Enums;

enum ShippingPaymentStatusEnum: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public static function trans($value)
    {
        return match ($value) {
            self::Unpaid->value => 'غير مدفوع',
            self::Pending->value => 'قيد التحقق',
            self::Paid->value => 'مدفوع',
            self::Failed->value => 'فشل الدفع',
            self::Refunded->value => 'تم رد المبلغ',
            default => $value,
        };
    }
}
