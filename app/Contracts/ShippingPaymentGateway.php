<?php

namespace App\Contracts;

use App\Models\ShippingRequest;

/**
 * The seam for the online payment gateway.
 *
 * checkout() starts a charge for the shipment's `shipping_cost` and returns whatever the app
 * needs to open it (e.g. ['checkout_url' => ...]), or null when no gateway is configured.
 *
 * The gateway integration must NOT mark the shipment paid from the client's redirect. It should
 * verify the charge server-side (webhook or a status lookup) and only then call
 * ShippingWorkflowService::markPaid().
 */
interface ShippingPaymentGateway
{
    public function checkout(ShippingRequest $shippingRequest): ?array;
}
