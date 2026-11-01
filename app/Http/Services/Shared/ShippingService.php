<?php

namespace App\Http\Services\Shared;

use App\Enums\StatusShippingRequestEnum;
use Illuminate\Http\Request;

class ShippingService
{
    public function __construct(protected \App\Http\Repositories\Shared\ShippingRepository $shippingRepository) {}

    public function storeShippingRequest($requestId, $responseId, $orderNumber, $nameOriginVendor, $cityOriginVendor, $addressOriginVendor, $latOriginVendor, $lngOriginVendor, $phoneOriginVendor, $length, $width, $height, $weight, $packages = null)
    {
        return $this->shippingRepository->storeShippingRequest([
            'request_id' => $requestId,
            'response_id' => $responseId,
            'order_number' => $orderNumber,
            'name_origin_vendor' => $nameOriginVendor,
            'city_origin_vendor' => $cityOriginVendor,
            'address_origin_vendor' => $addressOriginVendor,
            'lat_origin_vendor' => $latOriginVendor,
            'lng_origin_vendor' => $lngOriginVendor,
            'phone_origin_vendor' => $phoneOriginVendor,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'weight' => $weight,
            'packages' => $packages,
            // New shipments start in the manual-quote flow (see ShippingWorkflowService).
            'status' => StatusShippingRequestEnum::AwaitingCustomerApproval,
        ]);
    }
}
