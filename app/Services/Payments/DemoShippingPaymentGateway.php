<?php

namespace App\Services\Payments;

use App\Contracts\ShippingPaymentGateway;
use App\Http\Services\Shared\ShippingWorkflowService;
use App\Models\ShippingRequest;

/**
 * DEMO ONLY. No money moves: every payment "succeeds" immediately and the shipment is marked paid.
 *
 * Used while the payment methods for Saudi Arabia are being arranged, so the whole shipping flow
 * can be shown end to end. It is only bound when SHIPPING_PAYMENT_DEMO=true (see AppServiceProvider);
 * leave that off in production and bind the real gateway instead.
 */
class DemoShippingPaymentGateway implements ShippingPaymentGateway
{
    public function __construct(protected ShippingWorkflowService $workflow) {}

    public function checkout(ShippingRequest $shippingRequest): ?array
    {
        $this->workflow->markPaid($shippingRequest);

        return ['paid' => true, 'demo' => true];
    }
}
