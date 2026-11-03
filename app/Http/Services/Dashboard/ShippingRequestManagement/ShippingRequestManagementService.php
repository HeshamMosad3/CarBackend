<?php

namespace App\Http\Services\Dashboard\ShippingRequestManagement;

use App\Enums\StatusShippingRequestEnum;
use App\Exceptions\CustomResponseException;
use App\Http\Repositories\Dashboard\ShippingRequestManagement\ShippingRequestManagementRepository;
use App\Http\Services\BaseService;
use App\Models\ShippingRequest;
use App\Utils\ConfigUtils;
use App\Utils\OTOServiceUtils;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingRequestManagementService extends BaseService
{
    public function __construct(protected ShippingRequestManagementRepository $repo, protected OTOServiceUtils $otoServiceUtils) {}

    public function index(Request $request)
    {
        $searchValue = $request->input('search.value');

        $recordsCount =  $this->repo->getTotalRecordsCount(ShippingRequest::class);
        $recordsCountwithFilter = $this->repo->recordsCountShippingRequestWithFilter($searchValue, $request);
        $records = $this->repo->index($request, $searchValue);

        return $this->repo->formatResponseDataTables(
            draw: $request->input('draw'),
            recordsCount: $recordsCount,
            recordsCountwithFilter: $recordsCountwithFilter,
            records: $records
        );
    }
    public function groupCounts(): array
    {
        return $this->repo->groupCounts();
    }

    public function show($id)
    {
        $this->validate(['id' => $id], [
            'id' => 'required|integer|exists:shipping_requests,id',
        ]);

        $shippingRequest = $this->repo->show($id);
        $accessToken = $this->otoServiceUtils->getAccessTokenOTO();
        $deliveryCompanies = [];
        // All of OTO's delivery options (cheapest first): the admin picks one to book for legacy
        // shipments, and uses them as a price reference while a new-flow shipment is reviewed or quoted.
        $otoLookupStatuses = [
            StatusShippingRequestEnum::Pending,
            StatusShippingRequestEnum::ReadyForReview,
            StatusShippingRequestEnum::SearchingCarrier,
            StatusShippingRequestEnum::Paid, // needed to offer the optional OTO booking once the customer paid
        ];
        if ($shippingRequest->is_user_confirmed && in_array($shippingRequest->status, $otoLookupStatuses, true)) {
            $deliveryCompanies = $this->otoServiceUtils->checkDeliveryFeeAndGetCompanies(
                accessToken: $accessToken,
                originCity: $shippingRequest->city_origin_vendor,
                destinationCity: $shippingRequest->city_origin_dimensions,
                width: $shippingRequest->width,
                length: $shippingRequest->length,
                height: $shippingRequest->height,
                weight: $shippingRequest->weight
            );
        }
        return [
            'shippingRequest' => $shippingRequest,
            'deliveryCompanies' => $deliveryCompanies,
            'parties' => $this->repo->getParties($shippingRequest),
        ];
    }

    /**
     * Books the shipment with OTO. Throws a CustomResponseException with OTO's own message when OTO
     * refuses, so the admin sees why instead of a false "created successfully".
     */
    public function createOrderShippingRequest(Request $request)
    {
        $this->validate($request->all(), [
            'shippingRequestId' => 'required|integer|exists:shipping_requests,id',
            'deliveryOptionId' => 'required',
        ]);

        $shippingRequest = $this->repo->getShippingRequestDetailById($request->input('shippingRequestId'));
        if ($shippingRequest->oto_id) {
            throw new CustomResponseException(code: 422, message: 'تم إنشاء هذا الطلب لدى OTO مسبقاً');
        }

        $isLegacy = $shippingRequest->status->isLegacy();
        $customerName = $shippingRequest->name_destination ?: ($shippingRequest->customer_name ?? '');
        $accessToken = $this->otoServiceUtils->getAccessTokenOTO();

        // Shipments on the manual-quote flow were already paid for through the platform, so OTO must
        // not collect anything on delivery. Legacy ones keep the original cash-on-delivery setup.
        $orderValue = $isLegacy
            ? ConfigUtils::getAmountRateAppForCharge()
            : (float) ($shippingRequest->response_price ?: $shippingRequest->shipping_cost ?: 1);

        $packages = is_array($shippingRequest->packages) ? $shippingRequest->packages : [];
        $body = [
            // OTO identifies the order by this id, so it must differ for every shipment.
            "orderId" => $shippingRequest->order_number ?: 'SHIP-' . $shippingRequest->id,
            "createShipment" => true, // إنشاء الشحنة مباشرة
            "payment_method" => $isLegacy ? "cod" : "paid",
            "amount" => $orderValue,
            "amount_due" => 0,
            "deliveryOptionId" => $request->input('deliveryOptionId'),
            "currency" => "SAR",
            "packageCount" => max(1, count($packages)),
            "packageWeight" => (float) ($shippingRequest->weight ?: 1),
            "boxWidth" => (float) ($shippingRequest->width ?: 10),
            "boxLength" => (float) ($shippingRequest->length ?: 10),
            "boxHeight" => (float) ($shippingRequest->height ?: 10),
            "senderName" => $shippingRequest->company_sender_name ?? '',
            "senderInformation" => [
                "senderId" => (string) ($shippingRequest->vendor_user_id ?? $shippingRequest->id),
                "senderFullName" => $shippingRequest->company_sender_name ?? '',
                "senderMobile" => $shippingRequest->phone_origin_vendor ?? '',
                "senderCountry" => "SA",
                "senderCity" => \App\Utils\OTOServiceUtils::sanitizeCity($shippingRequest->city_origin_vendor),
                // OTO's documentation spells this field "senderAdressLine"; the correctly spelled key is kept too.
                "senderAdressLine" => $shippingRequest->address_origin_vendor ?? '',
                "senderAddressLine" => $shippingRequest->address_origin_vendor ?? '',
                "lat" => $shippingRequest->lat_origin_vendor,
                "lon" => $shippingRequest->lng_origin_vendor,
            ],
            "customer" => [
                "name" => $customerName,
                "mobile" => $shippingRequest->phone_origin_dimensions ?? '',
                "address" => $shippingRequest->address_origin_dimensions ?? '',
                "city" => \App\Utils\OTOServiceUtils::sanitizeCity($shippingRequest->city_origin_dimensions),
                "country" => "SA",
                "lat" => $shippingRequest->lat_destination,
                "lon" => $shippingRequest->lng_destination,
            ],
            "items" => [
                [
                    "name" => "box 1",
                    "price" => $orderValue,
                    "quantity" => 1,
                    "sku" => 'SHIP-' . $shippingRequest->id,
                ],
            ]
        ];

        $createOrderResponse = $this->otoServiceUtils->createOrder($body, $accessToken);
        Log::info('OTO createOrder response', ['shipping_request_id' => $shippingRequest->id, 'response' => $createOrderResponse]);

        if (!is_array($createOrderResponse) || empty($createOrderResponse['success'])) {
            $reason = is_array($createOrderResponse)
                ? ($createOrderResponse['message'] ?? $createOrderResponse['error'] ?? json_encode($createOrderResponse, JSON_UNESCAPED_UNICODE))
                : 'لا يوجد رد من OTO';
            throw new CustomResponseException(code: 422, message: 'رفضت OTO إنشاء الطلب: ' . $reason);
        }

        $update = ['oto_id' => $createOrderResponse['otoId']];

        // New-flow shipments change status only through ShippingWorkflowService, so booking
        // with OTO just records its id there; legacy ones keep moving to InProgress as before.
        if ($isLegacy) {
            $update['status'] = StatusShippingRequestEnum::InProgress;
        }

        $shippingRequest->update($update);
    }
}