<?php

namespace App\Http\Services\Shared;

use App\Enums\Notifications\NotificationCategoryEnum;
use App\Enums\ShippingPaymentStatusEnum;
use App\Enums\StatusShippingRequestEnum as Status;
use App\Exceptions\CustomResponseException;
use App\Models\ShippingRequest;
use App\Traits\NotificationsTrait;
use App\Utils\ConfigUtils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every status change of a new-flow shipment goes through here, so the rules in
 * StatusShippingRequestEnum::allowedNext() can't be bypassed and both parties are
 * notified consistently. Payment is deliberately NOT settable through transition():
 * only markPaid() may do it, and it is meant to be called by the payment gateway
 * integration once a charge is verified server-side.
 */
class ShippingWorkflowService
{
    use NotificationsTrait;

    /**
     * @param array $attributes Extra columns to set with the change (shipping_cost, carrier_name, ...).
     */
    public function transition(ShippingRequest $shippingRequest, Status $to, array $attributes = []): ShippingRequest
    {
        if ($to === Status::Paid) {
            throw new CustomResponseException(code: 422, message: 'لا يمكن تأكيد الدفع إلا من بوابة الدفع');
        }

        return $this->apply($shippingRequest, $to, $attributes);
    }

    /**
     * Records a payment confirmed by the gateway. Idempotent: calling it again for a
     * shipment that is already paid returns it unchanged instead of charging twice.
     */
    public function markPaid(ShippingRequest $shippingRequest): ShippingRequest
    {
        return $this->apply($shippingRequest, Status::Paid, [
            'payment_status' => ShippingPaymentStatusEnum::Paid->value,
            'paid_at' => now(),
        ], idempotentIfAlready: true);
    }

    /** Admin records that a paid amount was returned to the customer (done manually, outside the app). */
    public function markRefunded(ShippingRequest $shippingRequest, ?string $note = null): ShippingRequest
    {
        return DB::transaction(function () use ($shippingRequest, $note) {
            $fresh = ShippingRequest::whereKey($shippingRequest->id)->lockForUpdate()->firstOrFail();

            if ($fresh->payment_status !== ShippingPaymentStatusEnum::Paid->value) {
                throw new CustomResponseException(code: 422, message: 'لا يوجد مبلغ مدفوع لردّه');
            }
            if (!in_array($fresh->status, [Status::Canceled, Status::Unavailable], true)) {
                throw new CustomResponseException(code: 422, message: 'لا يُرد المبلغ إلا بعد إلغاء الشحن أو تعذّر توفيره');
            }

            $fresh->update([
                'payment_status' => ShippingPaymentStatusEnum::Refunded->value,
                'refunded_at' => now(),
                'refund_note' => $note,
            ]);

            $this->notifyCustomer($fresh, 'تم رد مبلغ الشحن', 'تم رد رسوم الشحن للطلب رقم ( ' . $fresh->request_id . ' )');

            return $fresh;
        });
    }

    /**
     * The customer canceled the original request: close the open shipments that haven't been
     * paid yet. A paid shipment is left for the admin to decide (finish it or refund).
     */
    public function cancelOpenForRequest(int $requestId, string $reason): void
    {
        ShippingRequest::where('request_id', $requestId)->whereNotNull('active_key')->each(function (ShippingRequest $sr) use ($reason) {
            try {
                if ($sr->payment_status === ShippingPaymentStatusEnum::Paid->value) {
                    $this->notifyToAdmin(
                        'طلب ملغي بشحنة مدفوعة',
                        'ألغى الزبون الطلب رقم ( ' . $requestId . ' ) وشحنته مدفوعة، يلزم قرارك: إكمال الشحن أو رد المبلغ',
                        NotificationCategoryEnum::ShippingRequest,
                        $sr->id
                    );
                    return;
                }

                $this->apply($sr, Status::Canceled, ['cancel_reason' => $reason]);
            } catch (Throwable $e) {
                Log::warning('Failed to cancel shipping ' . $sr->id . ' for request ' . $requestId . ': ' . $e->getMessage());
            }
        });
    }

    /** Paid shipments that ended without delivery and still owe the customer money. */
    public function needsRefund(ShippingRequest $shippingRequest): bool
    {
        return $shippingRequest->payment_status === ShippingPaymentStatusEnum::Paid->value
            && in_array($shippingRequest->status, [Status::Canceled, Status::Unavailable], true);
    }

    /**
     * Cancels unpaid quotes past their deadline and sends the one-off reminder.
     *
     * @return array{canceled:int, reminded:int}
     */
    public function expireUnpaid(): array
    {
        $canceled = 0;
        $reminded = 0;

        ShippingRequest::where('status', Status::AwaitingPayment->value)
            ->where('payment_status', '!=', ShippingPaymentStatusEnum::Paid->value)
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<=', now())
            ->each(function (ShippingRequest $sr) use (&$canceled) {
                try {
                    $this->apply($sr, Status::Canceled, ['cancel_reason' => 'انتهت مهلة دفع رسوم الشحن']);
                    $canceled++;
                } catch (Throwable $e) {
                    Log::warning('Failed to expire shipping request ' . $sr->id . ': ' . $e->getMessage());
                }
            });

        ShippingRequest::where('status', Status::AwaitingPayment->value)
            ->where('payment_status', '!=', ShippingPaymentStatusEnum::Paid->value)
            ->whereNull('payment_reminded_at')
            ->whereNotNull('cost_sent_at')
            ->where('cost_sent_at', '<=', now()->subHours(ConfigUtils::getShippingPaymentReminderAfterHours()))
            ->each(function (ShippingRequest $sr) use (&$reminded) {
                $sr->update(['payment_reminded_at' => now()]);
                $this->notifyCustomer(
                    $sr,
                    'تذكير بدفع رسوم الشحن',
                    'لم يتم دفع رسوم الشحن للطلب رقم ( ' . $sr->request_id . ' ) بعد، المبلغ ' . $sr->shipping_cost . ' ريال. سيُلغى الطلب تلقائياً بتاريخ ' . $sr->payment_due_at?->format('Y-m-d H:i') . '.'
                );
                $reminded++;
            });

        return ['canceled' => $canceled, 'reminded' => $reminded];
    }

    private function apply(ShippingRequest $shippingRequest, Status $to, array $attributes, bool $idempotentIfAlready = false): ShippingRequest
    {
        $changed = null;

        $result = DB::transaction(function () use ($shippingRequest, $to, $attributes, $idempotentIfAlready, &$changed) {
            // Re-read under a lock: two admins (or an admin and the payment webhook) must not race each other.
            $fresh = ShippingRequest::whereKey($shippingRequest->id)->lockForUpdate()->firstOrFail();
            $from = $fresh->status;

            if ($idempotentIfAlready && $from === $to) {
                return $fresh;
            }

            if (!($from instanceof Status) || !$from->canTransitionTo($to)) {
                throw new CustomResponseException(code: 422, message: 
                    'لا يمكن نقل الشحنة من "' . Status::trans($from?->value) . '" إلى "' . Status::trans($to->value) . '"'
                );
            }

            $attributes = $this->guard($fresh, $to, $attributes);

            $fresh->fill($attributes);
            $fresh->status = $to;
            $fresh->status_changed_at = now();
            $fresh->save();

            $changed = $fresh;
            return $fresh;
        });

        // Notify only after the transaction committed, and never let a push failure undo the change.
        if ($changed) {
            $this->notifyStatusChange($changed);
            $this->broadcastStatusChange($changed);
        }

        return $result;
    }

    /** Live update for the apps; a failure here must never undo or fail the status change. */
    private function broadcastStatusChange(ShippingRequest $sr): void
    {
        try {
            $customerId = DB::table('request_customers')->where('id', $sr->request_id)->value('user_id');
            $vendorUserId = DB::table('request_responses')
                ->join('vendors', 'vendors.id', '=', 'request_responses.vendor_id')
                ->where('request_responses.id', $sr->response_id)
                ->value('vendors.user_id');

            $userIds = array_values(array_unique(array_filter([(int) $customerId, (int) $vendorUserId])));
            if ($userIds) {
                event(new \App\Events\ShippingStatusChanged($userIds, $sr->id, (int) $sr->request_id, (int) $sr->response_id, $sr->status->value));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Preconditions and derived columns for specific target statuses. */
    private function guard(ShippingRequest $sr, Status $to, array $attributes): array
    {
        if ($to === Status::AwaitingPayment) {
            $cost = (float) ($attributes['shipping_cost'] ?? $sr->shipping_cost);
            if ($cost <= 0) {
                throw new CustomResponseException(code: 422, message: 'أدخل تكلفة الشحن قبل إرسالها إلى الزبون');
            }
            $attributes['shipping_cost'] = $cost;
            $attributes['cost_sent_at'] = now();
            $attributes['payment_due_at'] = now()->addDays(ConfigUtils::getShippingPaymentWindowDays());
            $attributes['payment_reminded_at'] = null;
            $attributes['payment_status'] = ShippingPaymentStatusEnum::Unpaid->value;
        }

        if ($to === Status::BookingConfirmed) {
            if ($sr->payment_status !== ShippingPaymentStatusEnum::Paid->value) {
                throw new CustomResponseException(code: 422, message: 'لا يمكن تأكيد الحجز قبل تأكيد دفع رسوم الشحن');
            }
            if (empty($attributes['carrier_name'] ?? $sr->carrier_name)) {
                throw new CustomResponseException(code: 422, message: 'أدخل اسم شركة الشحن لتأكيد الحجز');
            }
        }

        return $attributes;
    }

    private function notifyStatusChange(ShippingRequest $sr): void
    {
        $order = ' ( ' . $sr->request_id . ' )';
        $status = $sr->status;

        try {
            switch ($status) {
                // AwaitingCustomerApproval is announced by the chat push that carries the vendor's message.
                case Status::ReadyForReview:
                    $this->notifyCustomer($sr, 'تم استلام بيانات الشحن', 'بيانات الشحن للطلب رقم' . $order . ' قيد المراجعة');
                    $this->notifyToAdmin('طلب شحن جاهز للمراجعة', 'طلب الشحن للطلب رقم' . $order . ' جاهز للمراجعة', NotificationCategoryEnum::ShippingRequest, $sr->id);
                    break;

                case Status::SearchingCarrier:
                    $this->notifyBoth($sr, 'جارٍ البحث عن شركة شحن', 'نبحث عن شركة شحن مناسبة للطلب رقم' . $order);
                    break;

                case Status::AwaitingPayment:
                    $this->notifyCustomer($sr, 'تم تحديد تكلفة الشحن', 'تكلفة شحن الطلب رقم' . $order . ' هي ' . $sr->shipping_cost . ' ريال. يرجى الدفع خلال ' . ConfigUtils::getShippingPaymentWindowDays() . ' أيام');
                    $this->notifyVendor($sr, 'تم تحديد تكلفة الشحن', 'تم تحديد تكلفة شحن الطلب رقم' . $order . '، بانتظار دفع الزبون');
                    break;

                case Status::Paid:
                    $this->notifyBoth($sr, 'تم دفع رسوم الشحن', 'تم دفع رسوم شحن الطلب رقم' . $order);
                    $this->notifyToAdmin('تم دفع رسوم شحن', 'تم دفع رسوم شحن الطلب رقم' . $order . '، يمكنك استكمال الحجز', NotificationCategoryEnum::ShippingRequest, $sr->id);
                    break;

                case Status::BookingConfirmed:
                    $this->notifyBoth($sr, 'تم تأكيد حجز الشحن', 'تم حجز شحن الطلب رقم' . $order . ' لدى ' . $sr->carrier_name . ($sr->tracking_number ? ' - رقم التتبع ' . $sr->tracking_number : ''));
                    break;

                case Status::PickedUp:
                    $this->notifyBoth($sr, 'تم استلام الشحنة', 'تم استلام شحنة الطلب رقم' . $order . ' من التاجر');
                    break;

                case Status::InTransit:
                    $this->notifyBoth($sr, 'الشحنة في الطريق', 'شحنة الطلب رقم' . $order . ' في الطريق');
                    break;

                case Status::Delivered:
                    $this->notifyBoth($sr, 'تم تسليم الشحنة', 'تم تسليم شحنة الطلب رقم' . $order);
                    break;

                case Status::Unavailable:
                    $refund = $sr->payment_status === ShippingPaymentStatusEnum::Paid->value ? ' وسيتم رد المبلغ المدفوع.' : '';
                    $this->notifyBoth($sr, 'تعذّر توفير الشحن', 'تعذّر توفير خدمة الشحن للطلب رقم' . $order . '، يمكنكما ترتيب الشحن بأنفسكما.' . $refund);
                    break;

                case Status::Canceled:
                    $reason = $sr->cancel_reason ? ' (' . $sr->cancel_reason . ')' : '';
                    $this->notifyBoth($sr, 'أُلغي طلب الشحن', 'أُلغي طلب شحن الطلب رقم' . $order . $reason);
                    break;

                default:
                    break;
            }
        } catch (Throwable $e) {
            Log::warning('Shipping status notification failed for shipping ' . $sr->id . ': ' . $e->getMessage());
        }
    }

    private function notifyBoth(ShippingRequest $sr, string $title, string $body): void
    {
        $this->notifyCustomer($sr, $title, $body);
        $this->notifyVendor($sr, $title, $body);
    }

    private function notifyCustomer(ShippingRequest $sr, string $title, string $body): void
    {
        $userId = DB::table('request_customers')->where('id', $sr->request_id)->value('user_id');
        $this->notifyUser($userId, $title, $body, $sr);
    }

    private function notifyVendor(ShippingRequest $sr, string $title, string $body): void
    {
        $userId = DB::table('request_responses')
            ->join('vendors', 'vendors.id', '=', 'request_responses.vendor_id')
            ->where('request_responses.id', $sr->response_id)
            ->value('vendors.user_id');
        $this->notifyUser($userId, $title, $body, $sr);
    }

    private function notifyUser($userId, string $title, string $body, ShippingRequest $sr): void
    {
        if (!$userId) {
            return;
        }

        // Generic on purpose: ShippingRequest would deep-link into a chat and bump the chat badge.
        $this->notifyByID(
            userId: $userId,
            title: $title,
            body: $body,
            category: NotificationCategoryEnum::Generic,
            targetId: $sr->id,
        );
    }
}
