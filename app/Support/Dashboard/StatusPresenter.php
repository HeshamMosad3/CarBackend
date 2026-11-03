<?php

namespace App\Support\Dashboard;

use App\Enums\ShippingPaymentStatusEnum;
use App\Enums\StatusShippingRequestEnum;

/**
 * One place that decides how every status in the admin dashboard is named and coloured.
 * The same map is handed to the browser (window.DASH_STATUS) so server-rendered pages and
 * DataTables rows always agree.
 *
 * Tones are Bootstrap colour names: primary, success, warning, danger, info, secondary.
 */
class StatusPresenter
{
    public static function all(): array
    {
        $shipping = [];
        foreach (StatusShippingRequestEnum::cases() as $case) {
            $shipping[$case->value] = [StatusShippingRequestEnum::trans($case->value), self::shippingTone($case)];
        }

        $payment = [];
        foreach (ShippingPaymentStatusEnum::cases() as $case) {
            $payment[$case->value] = [ShippingPaymentStatusEnum::trans($case->value), match ($case) {
                ShippingPaymentStatusEnum::Paid => 'success',
                ShippingPaymentStatusEnum::Pending => 'warning',
                ShippingPaymentStatusEnum::Failed => 'danger',
                default => 'secondary',
            }];
        }

        return [
            'shipping' => $shipping,
            'payment' => $payment,
            'request' => [
                'open' => ['مفتوح', 'success'],
                'closed' => ['مغلق', 'secondary'],
                'canceled' => ['ملغي', 'danger'],
                'completed' => ['مكتمل', 'primary'],
            ],
            'user' => [
                'Pending' => ['بانتظار المراجعة', 'warning'],
                'Active' => ['نشط', 'success'],
                'Inactive' => ['غير نشط', 'secondary'],
                'Suspended' => ['معلّق', 'warning'],
                'Rejected' => ['مرفوض', 'danger'],
            ],
            'category' => [
                'Active' => ['مفعّل', 'success'],
                'Inactive' => ['معطّل', 'secondary'],
                'Soon' => ['قريباً', 'info'],
            ],
            'complaint' => [
                'new' => ['جديدة', 'danger'],
                'under_review' => ['قيد المراجعة', 'warning'],
                'resolved' => ['تم الحل', 'success'],
                'rejected' => ['مرفوضة', 'secondary'],
                'closed' => ['مغلقة', 'secondary'],
            ],
            'response' => [
                'available' => ['متوفر', 'success'],
                'available_with_difference' => ['متوفر مع اختلاف', 'warning'],
                'unavailable' => ['غير متوفر', 'danger'],
            ],
        ];
    }

    /** @return array{0:string,1:string} [label, tone] */
    public static function get(string $type, $value): array
    {
        $value = $value instanceof \BackedEnum ? $value->value : (string) $value;

        return self::all()[$type][$value] ?? [$value !== '' ? $value : '-', 'secondary'];
    }

    private static function shippingTone(StatusShippingRequestEnum $status): string
    {
        return match ($status) {
            StatusShippingRequestEnum::Completed, StatusShippingRequestEnum::Delivered => 'success',
            StatusShippingRequestEnum::Canceled, StatusShippingRequestEnum::Unavailable => 'danger',
            StatusShippingRequestEnum::Pending, StatusShippingRequestEnum::ReadyForReview,
            StatusShippingRequestEnum::AwaitingPayment => 'warning',
            StatusShippingRequestEnum::AwaitingCustomerApproval,
            StatusShippingRequestEnum::AwaitingCustomerData => 'secondary',
            StatusShippingRequestEnum::Paid, StatusShippingRequestEnum::BookingConfirmed => 'primary',
            default => 'info',
        };
    }
}
