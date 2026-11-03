<?php

namespace App\Support\Dashboard;

use App\Enums\ShippingPaymentStatusEnum;
use App\Enums\StatusShippingRequestEnum as Ship;
use App\Enums\StatusUserEnum;
use App\Enums\user\UserRoleEnum;
use App\Models\Complaint;
use App\Models\ShippingRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * What is waiting on the admin right now. Drives the sidebar badges, the header bell and the
 * "needs your attention" panel on the home page, so they always show the same numbers.
 */
class AdminAttention
{
    private static ?array $counts = null;

    /** Shipments the admin has to move forward themselves. */
    public const SHIPPING_ACTION_STATUSES = [Ship::ReadyForReview, Ship::SearchingCarrier, Ship::Paid];

    public static function shippingNeedsAction(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereIn('shipping_requests.status', array_map(fn ($s) => $s->value, self::SHIPPING_ACTION_STATUSES))
                ->orWhere(function ($q) {
                    // Legacy flow: confirmed by the customer and still waiting to be booked.
                    $q->where('shipping_requests.status', Ship::Pending->value)
                        ->where('shipping_requests.is_user_confirmed', true)
                        ->whereNull('shipping_requests.oto_id');
                });
        });
    }

    public static function shippingNeedsRefund(Builder $query): Builder
    {
        return $query->where('shipping_requests.payment_status', ShippingPaymentStatusEnum::Paid->value)
            ->whereIn('shipping_requests.status', [Ship::Canceled->value, Ship::Unavailable->value]);
    }

    public static function counts(): array
    {
        if (self::$counts !== null) {
            return self::$counts;
        }

        try {
            $counts = [
                'joinRequests' => User::role(UserRoleEnum::Vendor->value)
                    ->where('status', StatusUserEnum::Pending->value)->count(),
                'shippingAction' => self::shippingNeedsAction(ShippingRequest::query())->count(),
                'shippingRefund' => self::shippingNeedsRefund(ShippingRequest::query())->count(),
                'complaints' => Complaint::where('status', 'new')->count(),
            ];
        } catch (\Throwable $e) {
            report($e);
            $counts = ['joinRequests' => 0, 'shippingAction' => 0, 'shippingRefund' => 0, 'complaints' => 0];
        }

        $counts['shipping'] = $counts['shippingAction'] + $counts['shippingRefund'];
        $counts['total'] = $counts['joinRequests'] + $counts['shipping'] + $counts['complaints'];

        return self::$counts = $counts;
    }
}
