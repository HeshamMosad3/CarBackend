<?php

namespace App\Http\Controllers\API\V1\Shared;

use App\Contracts\ShippingPaymentGateway;
use App\Enums\ShippingPaymentStatusEnum;
use App\Enums\StatusShippingRequestEnum as Status;
use App\Exceptions\CustomResponseException;
use App\Http\Controllers\Controller;
use App\Http\Services\Shared\ShippingWorkflowService;
use App\Models\ShippingRequest;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class ShippingController extends Controller
{
    /** Statuses in which the customer may still walk away, as long as nothing was paid. */
    private const CUSTOMER_CAN_CANCEL = [
        Status::AwaitingCustomerApproval,
        Status::AwaitingCustomerData,
        Status::ReadyForReview,
        Status::SearchingCarrier,
        Status::AwaitingPayment,
    ];

    public function __construct(
        protected ShippingWorkflowService $workflow,
        protected ShippingPaymentGateway $gateway,
    ) {}

    public function showByResponse(int $requestId, int $responseId)
    {
        $shippingRequest = ShippingRequest::findOpenFor($requestId, $responseId)
            ?? ShippingRequest::where('request_id', $requestId)->where('response_id', $responseId)->latest('id')->first();

        if (!$shippingRequest) {
            return buildApiResponseHelper(true, 'لا يوجد طلب شحن', null);
        }

        $role = $this->roleOf($shippingRequest);
        if (!$role) {
            return buildApiResponseHelper(false, 'غير مصرح لك بعرض هذا الشحن');
        }

        return buildApiResponseHelper(true, 'تم التحميل بنجاح', $this->payload($shippingRequest, $role));
    }

    /**
     * Shipments waiting on the customer to do something (approve, complete the data, pay).
     * Steps that only wait on the admin, the carrier or the system are left out on purpose:
     * there is nothing for the user to do there, and the status screen already covers them.
     */
    public function pendingActions()
    {
        $me = getCurrUserIdHelper();

        $rows = ShippingRequest::query()
            ->join('request_customers', 'request_customers.id', '=', 'shipping_requests.request_id')
            ->where('request_customers.user_id', $me)
            ->whereIn('shipping_requests.status', [
                Status::AwaitingCustomerApproval->value,
                Status::AwaitingCustomerData->value,
                Status::AwaitingPayment->value,
            ])
            ->select('shipping_requests.*')
            ->orderByDesc('shipping_requests.id')
            ->get();

        $items = [];
        $seen = [];
        foreach ($rows as $sr) {
            $key = $sr->request_id . ':' . $sr->response_id;
            if (isset($seen[$key])) {
                continue;
            }

            $action = match ($sr->status) {
                Status::AwaitingCustomerApproval => ['approve', 'وافق على طلب الشحن'],
                Status::AwaitingCustomerData => ['complete_data', 'أكمل بيانات الشحن'],
                Status::AwaitingPayment => $this->canPay($sr) ? ['pay', 'ادفع رسوم الشحن'] : null,
                default => null,
            };
            if (!$action) {
                continue;
            }

            $conversation = DB::table('conversations')
                ->leftJoin('vendors', function ($join) {
                    $join->on('vendors.user_id', '=', 'conversations.vendor_id')
                        ->orOn('vendors.id', '=', 'conversations.vendor_id');
                })
                ->leftJoin('users as vendor_user', function ($join) {
                    $join->on('vendor_user.id', '=', 'vendors.user_id')
                        ->orOn('vendor_user.id', '=', 'conversations.vendor_id');
                })
                ->where('conversations.user_id', $me)
                ->where('conversations.request_id', $sr->request_id)
                ->where('conversations.response_id', $sr->response_id)
                ->orderByDesc('conversations.id')
                ->select([
                    'conversations.id',
                    'conversations.vendor_id',
                    DB::raw('COALESCE(NULLIF(NULLIF(vendors.company_name_ar, "غير محدد"), ""), vendor_user.name, "غير محدد") as receiver_name'),
                    DB::raw('vendor_user.logo as receiver_logo'),
                ])
                ->first();

            // The action happens inside the chat, so a shipment without a conversation has nowhere to go.
            if (!$conversation) {
                continue;
            }

            $seen[$key] = true;
            $items[] = [
                'shipping_id' => $sr->id,
                'status' => $sr->status->value,
                'action' => $action[0],
                'action_text' => $action[1],
                'conversation_id' => $conversation->id,
                'request_id' => $sr->request_id,
                'response_id' => $sr->response_id,
                'vendor_id' => $conversation->vendor_id,
                'receiver_name' => $conversation->receiver_name,
                'receiver_logo' => $conversation->receiver_logo,
            ];
        }

        return buildApiResponseHelper(true, 'تم التحميل بنجاح', ['items' => $items]);
    }

    /** Customer opened the "approve shipping" form: records that the data is now being completed. */
    public function approve(int $id)
    {
        return $this->asCustomer($id, function (ShippingRequest $sr) {
            if ($sr->status === Status::AwaitingCustomerApproval) {
                $sr = $this->workflow->transition($sr, Status::AwaitingCustomerData);
            } elseif ($sr->status !== Status::AwaitingCustomerData) {
                return buildApiResponseHelper(false, 'لا يمكن الموافقة على الشحن في حالته الحالية');
            }

            return buildApiResponseHelper(true, 'تمت الموافقة على الشحن، أكمل بياناتك', $this->payload($sr, 'customer'));
        });
    }

    public function cancel(int $id)
    {
        return $this->asCustomer($id, function (ShippingRequest $sr) {
            if (!in_array($sr->status, self::CUSTOMER_CAN_CANCEL, true) || $sr->payment_status === ShippingPaymentStatusEnum::Paid->value) {
                return buildApiResponseHelper(false, 'لا يمكن إلغاء الشحن في حالته الحالية');
            }

            $sr = $this->workflow->transition($sr, Status::Canceled, ['cancel_reason' => 'ألغاه الزبون']);

            return buildApiResponseHelper(true, 'تم إلغاء طلب الشحن', $this->payload($sr, 'customer'));
        });
    }

    public function pay(int $id)
    {
        return $this->asCustomer($id, function (ShippingRequest $sr) {
            // A second tap on "pay" after it already went through is not an error.
            if ($sr->payment_status === ShippingPaymentStatusEnum::Paid->value) {
                return buildApiResponseHelper(true, 'تم دفع رسوم الشحن بنجاح', ['paid' => true] + $this->payload($sr, 'customer'));
            }

            if (!$this->canPay($sr)) {
                return buildApiResponseHelper(false, 'لا يمكن دفع رسوم الشحن في الوقت الحالي');
            }

            $checkout = $this->gateway->checkout($sr);
            if ($checkout === null) {
                return buildApiResponseHelper(false, 'الدفع الإلكتروني غير متاح حالياً');
            }

            if ($checkout['paid'] ?? false) {
                return buildApiResponseHelper(true, 'تمت عملية الدفع بنجاح', $checkout + $this->payload($sr->fresh(), 'customer'));
            }

            return buildApiResponseHelper(true, 'تم بدء عملية الدفع', $checkout);
        });
    }

    /** The vendor's saved pickup point, used to pre-fill the shipping form. */
    public function storeAddress()
    {
        $vendor = Vendor::where('user_id', getCurrUserIdHelper())->first();
        if (!$vendor) {
            return buildApiResponseHelper(false, 'غير مصرح');
        }

        return buildApiResponseHelper(true, 'تم التحميل بنجاح', [
            'name' => $vendor->store_name,
            'city' => $vendor->store_city,
            'address' => $vendor->store_address,
            'lat' => $vendor->store_lat,
            'lng' => $vendor->store_lng,
            'phone' => $vendor->phone_contact,
        ]);
    }

    private function asCustomer(int $id, callable $action)
    {
        $shippingRequest = ShippingRequest::find($id);
        if (!$shippingRequest || $this->roleOf($shippingRequest) !== 'customer') {
            return buildApiResponseHelper(false, 'لا يوجد طلب شحن');
        }

        try {
            return $action($shippingRequest);
        } catch (CustomResponseException $e) {
            return buildApiResponseHelper(false, $e->getMessage());
        }
    }

    private function roleOf(ShippingRequest $sr): ?string
    {
        $me = getCurrUserIdHelper();

        $customerId = DB::table('request_customers')->where('id', $sr->request_id)->value('user_id');
        if ($customerId && (int) $customerId === $me) {
            return 'customer';
        }

        $vendorUserId = DB::table('request_responses')
            ->join('vendors', 'vendors.id', '=', 'request_responses.vendor_id')
            ->where('request_responses.id', $sr->response_id)
            ->value('vendors.user_id');
        if ($vendorUserId && (int) $vendorUserId === $me) {
            return 'vendor';
        }

        return null;
    }

    private function canPay(ShippingRequest $sr): bool
    {
        return $sr->status === Status::AwaitingPayment
            && in_array($sr->payment_status, [ShippingPaymentStatusEnum::Unpaid->value, ShippingPaymentStatusEnum::Failed->value], true)
            && $sr->shipping_cost > 0
            && (!$sr->payment_due_at || $sr->payment_due_at->isFuture());
    }

    private function payload(ShippingRequest $sr, string $role): array
    {
        $data = [
            'id' => $sr->id,
            'request_id' => $sr->request_id,
            'response_id' => $sr->response_id,
            'status' => $sr->status->value,
            'status_label' => Status::trans($sr->status->value),
            'length' => $sr->length,
            'width' => $sr->width,
            'height' => $sr->height,
            'weight' => $sr->weight,
            'packages' => $sr->packages,
            'carrier_name' => $sr->carrier_name,
            'tracking_number' => $sr->tracking_number,
            'cancel_reason' => $sr->cancel_reason,
            'status_changed_at' => $sr->status_changed_at?->format('Y-m-d H:i'),
        ];

        if ($role === 'customer') {
            $data += [
                'shipping_cost' => $sr->shipping_cost,
                'payment_status' => $sr->payment_status,
                'payment_status_label' => ShippingPaymentStatusEnum::trans($sr->payment_status),
                'payment_due_at' => $sr->payment_due_at?->format('Y-m-d H:i'),
                'pickup_details' => $sr->pickup_details,
                'pickup_method' => $sr->pickup_method,
                'pickup_method_label' => \App\Support\Shipping\PickupMethod::label($sr->pickup_method),
                // Shipments from before the manual-quote flow that the customer never confirmed can
                // still be completed through the same form.
                'needs_data' => in_array($sr->status, [Status::AwaitingCustomerApproval, Status::AwaitingCustomerData], true)
                    || ($sr->status === Status::Pending && !$sr->is_user_confirmed),
                'can_pay' => $this->canPay($sr),
                'can_cancel' => in_array($sr->status, self::CUSTOMER_CAN_CANCEL, true) && $sr->payment_status !== ShippingPaymentStatusEnum::Paid->value,
            ];
        }

        return $data;
    }
}
