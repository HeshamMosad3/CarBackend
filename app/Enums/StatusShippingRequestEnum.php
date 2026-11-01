<?php

namespace App\Enums;


enum StatusShippingRequestEnum: string
{
    // Legacy values: rows created before the manual-quote flow. Left untouched so existing
    // shipments (and installed app builds that compare against 'Pending') keep working.
    case Pending = 'Pending';
    case InProgress = 'InProgress';
    case Completed = 'Completed';

    case AwaitingCustomerApproval = 'AwaitingCustomerApproval';
    case AwaitingCustomerData = 'AwaitingCustomerData';
    case ReadyForReview = 'ReadyForReview';
    case SearchingCarrier = 'SearchingCarrier';
    case AwaitingPayment = 'AwaitingPayment';
    case Paid = 'Paid';
    case BookingConfirmed = 'BookingConfirmed';
    case PickedUp = 'PickedUp';
    case InTransit = 'InTransit';
    case Delivered = 'Delivered';
    case Unavailable = 'Unavailable';
    case Canceled = 'Canceled';

    public static function trans($value)
    {
        return match ($value) {
            self::Pending->value => 'قيد الانتظار',
            self::InProgress->value => 'قيد التنفيذ',
            self::Completed->value => 'مكتمل',
            self::AwaitingCustomerApproval->value => 'بانتظار موافقة الزبون',
            self::AwaitingCustomerData->value => 'بانتظار استكمال البيانات',
            self::ReadyForReview->value => 'جاهز للمراجعة الإدارية',
            self::SearchingCarrier->value => 'جارٍ البحث عن شركة شحن',
            self::AwaitingPayment->value => 'بانتظار دفع رسوم الشحن',
            self::Paid->value => 'تم دفع رسوم الشحن',
            self::BookingConfirmed->value => 'تم تأكيد الحجز',
            self::PickedUp->value => 'تم استلام الشحنة',
            self::InTransit->value => 'في الطريق',
            self::Delivered->value => 'تم التسليم',
            self::Unavailable->value => 'تعذّر توفير الشحن',
            self::Canceled->value => 'أُلغي طلب الشحن',
            default => $value,
        };
    }

    public function isLegacy(): bool
    {
        return in_array($this, [self::Pending, self::InProgress, self::Completed], true);
    }

    /** No further transitions are possible. Legacy values count as closed to the new workflow. */
    public function isTerminal(): bool
    {
        return $this->isLegacy() || in_array($this, [self::Delivered, self::Unavailable, self::Canceled], true);
    }

    /** The single source of truth for which status may follow which. */
    public function allowedNext(): array
    {
        return match ($this) {
            self::AwaitingCustomerApproval => [self::AwaitingCustomerData, self::ReadyForReview, self::Canceled],
            self::AwaitingCustomerData => [self::ReadyForReview, self::Canceled],
            self::ReadyForReview => [self::SearchingCarrier, self::Unavailable, self::Canceled],
            self::SearchingCarrier => [self::AwaitingPayment, self::Unavailable, self::Canceled],
            // Back to SearchingCarrier lets the admin correct a quote that hasn't been paid yet.
            self::AwaitingPayment => [self::Paid, self::SearchingCarrier, self::Unavailable, self::Canceled],
            self::Paid => [self::BookingConfirmed, self::Unavailable, self::Canceled],
            self::BookingConfirmed => [self::PickedUp, self::Canceled],
            self::PickedUp => [self::InTransit],
            self::InTransit => [self::Delivered],
            default => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
