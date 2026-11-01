<?php

namespace App\Services\Payments;

use App\Contracts\ShippingPaymentGateway;
use App\Models\ShippingRequest;

/** Bound until a real gateway is added: nothing can be paid, and nothing is ever marked paid. */
class NullShippingPaymentGateway implements ShippingPaymentGateway
{
    public function checkout(ShippingRequest $shippingRequest): ?array
    {
        return null;
    }
}
