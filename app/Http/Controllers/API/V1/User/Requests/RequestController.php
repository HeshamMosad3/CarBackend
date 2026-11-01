<?php

namespace App\Http\Controllers\API\V1\User\Requests;

use App\Enums\Notifications\NotificationCategoryEnum;
use App\Enums\StatusShippingRequestEnum;
use App\Exceptions\CustomResponseException;
use App\Http\Services\Shared\ShippingWorkflowService;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Request\{CheckEligibleVendorsRequest, ConfirmOrderRequest, ConfirmPriceShippingRequest, ConfirmShippingRequest};
use App\Http\Services\User\Requests\RequestService;
use App\Models\ShippingRequest;
use App\Models\Vendor;
use App\Traits\NotificationsTrait;
use App\Utils\ConfigUtils;
use App\Utils\OTOServiceUtils;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class RequestController extends Controller
{
    use NotificationsTrait;

    public function __construct(
        protected RequestService $requestService,
        protected OTOServiceUtils $otoServiceUtils,
        protected ShippingWorkflowService $shippingWorkflow,
    ) {}

    public function checkEligibleVendors(CheckEligibleVendorsRequest $request)
    {
        $count = $this->requestService->countFilterEligibleVendors($request);

        return ($count == 0)
            ? buildApiResponseHelper(false, 'لم يتم العثور على شركات مؤهلة تلبي شروط ')
            : buildApiResponseHelper(true, 'تم العثور على ( ' . $count . ' ) شركة مؤهلة تلبي شروط طلبك');
    }

    public function confirmRequest(ConfirmOrderRequest $request)
    {
        DB::beginTransaction();
        try {
            ['eligibleVendors' => $eligibleVendors, 'requestId' => $newRequestId] = $this->requestService->confirmRequest($request);

            DB::commit();
            try {
                $this->notifyRequestToEligibleVendors($eligibleVendors, $newRequestId);
            } catch (\Throwable $notifEx) {
                \Illuminate\Support\Facades\Log::warning('Failed to dispatch notification on confirmRequest: ' . $notifEx->getMessage());
            }

            return buildApiResponseHelper(true, 'تم إرسال الطلب بنجاح ... سيتم الرد عليك من خلال الشركات المؤهلة لاحقاً');
        } catch (Exception $e) {
            DB::rollBack();
            report($e);
            throw new CustomResponseException("حدث خطاء أثنا تأكيد الطلب ... الرجاء المحاولة مرة أخرى");
        }
    }

    public function ConfirmShippingRequest(ConfirmShippingRequest $request)
    {
        set_time_limit(60);
        try {
            $shippingRequest = ShippingRequest::findOpenFor((int) $request->requestId, (int) $request->responseId)
                ?? ShippingRequest::where('request_id', $request->requestId)->where('response_id', $request->responseId)->latest()->first();

            if (!$shippingRequest || !$this->ownsRequest((int) $request->requestId)) {
                return buildApiResponseHelper(false, 'لا يوجد شحنة لهذا الطلب');
            }

            // New shipments are quoted by hand by the admin, so there is no price to fetch here.
            // Shipments created before that flow keep the original OTO price-check path below.
            if (!$shippingRequest->status->isLegacy()) {
                return $this->submitCustomerShippingData($request, $shippingRequest);
            }

            $originCity = OTOServiceUtils::sanitizeCity($shippingRequest->city_origin_vendor);
            $destinationCity = OTOServiceUtils::sanitizeCity($request->cityOriginDimensions);

            $w = (float) ($shippingRequest->width ?: 10);
            $l = (float) ($shippingRequest->length ?: 10);
            $h = (float) ($shippingRequest->height ?: 10);
            $wt = (float) ($shippingRequest->weight ?: 1);

            $dataBody = [
                'originCity' => $originCity,
                'destinationCity' => $destinationCity,
                'boxes' => [
                    [
                        'boxName' => 'Box1',
                        'width' => $w,
                        'length' => $l,
                        'height' => $h,
                        'weight' => $wt,
                    ]
                ],
                'width' => $w,
                'length' => $l,
                'height' => $h,
                'weight' => $wt,
                'isCod' => true
            ];

            $token = $this->otoServiceUtils->getAccessTokenOTO();
            if (empty($token)) {
                Log::error('OTO Access Token is empty in ConfirmShippingRequest');
                return buildApiResponseHelper(false, 'تعذر الاتصال بشركة الشحن في الوقت الحالي ... الرجاء المحاولة لاحقاً');
            }

            $otoUrl = $this->otoServiceUtils->getBaseUrl();
            $response = Http::timeout(15)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])
                ->post($otoUrl . '/checkOTODeliveryFee', $dataBody);

            if ($response->status() === 401) {
                cache()->forget('oto_access_token');
                $token = $this->otoServiceUtils->getAccessTokenOTO();
                $response = Http::timeout(15)->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->post($otoUrl . '/checkOTODeliveryFee', $dataBody);
            }

            if ($response->ok()) {
                $result = $response->json();
                if (isset($result['success']) && $result['success'] == false) {
                    Log::warning('OTO Delivery Fee Warning: ', $result);
                    return buildApiResponseHelper(false, 'لا توجد شركات شحن متاحة لهذا المسار حالياً');
                }

                $companies = $result['deliveryCompany'] ?? [];
                if (empty($companies)) {
                    return buildApiResponseHelper(false, 'لا تتوفر شركات شحن متاحة حالياً');
                }

                $cheapest = collect($companies)->sortBy('price')->first();
                $cheapestPrice = $cheapest['price'] ?? 0;
                $shippingRequest->update([
                    'id_number_user' => $request->idNumberUser,
                    'city_origin_dimensions' => $destinationCity,
                    'address_origin_dimensions' => $request->addressOriginDimensions,
                    'phone_origin_dimensions' => $request->phoneOriginDimensions,
                    'fee_cheapest_shipping' => $cheapestPrice,
                    'amount_rate_app' => ConfigUtils::getAmountRateAppForCharge(),
                ]);

                return buildApiResponseHelper(true, 'السعر التقريبي للشحنة' . ' ' . ($cheapestPrice + ConfigUtils::getAmountRateAppForCharge()) . ' ريال' . ' - إضغط موافق لتاكيد الشحنة',  ['shippingRequestId' => $shippingRequest->id]);
            }

            Log::error('OTO Delivery Fee Error: ' . $response->status() . ' - ' . $response->body(), ['payload' => $dataBody]);
            return buildApiResponseHelper(false, 'تعذر جلب أسعار الشحن من شركة الشحن ... الرجاء المحاولة لاحقاً');
        } catch (Exception $e) {
            Log::error('ConfirmShippingRequest Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            report($e);
            return buildApiResponseHelper(false, 'حدث خطأ في تأكيد الشحنة ... الرجاء المحاولة مرة أخرى');
        }
    }

    private function ownsRequest(int $requestId): bool
    {
        return DB::table('request_customers')->where('id', $requestId)->where('user_id', getCurrUserIdHelper())->exists();
    }

    private function submitCustomerShippingData(ConfirmShippingRequest $request, ShippingRequest $shippingRequest)
    {
        if (!in_array($shippingRequest->status, [StatusShippingRequestEnum::AwaitingCustomerApproval, StatusShippingRequestEnum::AwaitingCustomerData], true)) {
            return buildApiResponseHelper(false, 'لا يمكن تعديل بيانات هذا الشحن في حالته الحالية');
        }

        $this->shippingWorkflow->transition($shippingRequest, StatusShippingRequestEnum::ReadyForReview, [
            'id_number_user' => $request->idNumberUser,
            // Stored in the English form OTO expects, same as the original flow.
            'city_origin_dimensions' => OTOServiceUtils::sanitizeCity($request->cityOriginDimensions),
            'address_origin_dimensions' => $request->addressOriginDimensions,
            'phone_origin_dimensions' => $request->phoneOriginDimensions,
            'name_destination' => $request->nameDestination,
            'lat_destination' => $request->latDestination,
            'lng_destination' => $request->lngDestination,
            'is_user_confirmed' => true,
        ]);

        \App\Jobs\SendNewShippingRequestNotificationJob::dispatch($shippingRequest->id);

        return buildApiResponseHelper(true, 'تم استلام بيانات الشحن وسيتم إشعارك بتكلفة الشحن بعد المراجعة', [
            'shippingRequestId' => $shippingRequest->id,
            'flow' => 'manual_quote',
        ]);
    }

    public function confirmPriceShippingRequest(ConfirmPriceShippingRequest $request)
    {
        $shippingRequest = ShippingRequest::find($request->id);
        if (!$shippingRequest || !$this->ownsRequest((int) $shippingRequest->request_id)) {
            return buildApiResponseHelper(false, 'حدث خطاء في تاكيد الشحنة ... الرجاء المحاولة مرة اخرى');
        }

        // Older app builds still call this after confirm-shipping-request. On the new flow the data
        // was already submitted there, so acknowledge without notifying the admin a second time.
        if (!$shippingRequest->status->isLegacy()) {
            return buildApiResponseHelper(true, 'تم تاكيد الشحنة بنجاح');
        }

        $updated = ShippingRequest::where('id', $request->id)->update([
            'is_user_confirmed' => true
        ]);

        if (!$updated)
            return buildApiResponseHelper(false, 'حدث خطاء في تاكيد الشحنة ... الرجاء المحاولة مرة اخرى');

        $this->notifyToAdmin('طلب شحنة جديد', 'هناك طلب شحنة جديد ... طلب شحنة جديد', NotificationCategoryEnum::ShippingRequest, $request->id);

        // Dispatch Email Notification to Admin Emails
        \App\Jobs\SendNewShippingRequestNotificationJob::dispatch($request->id);

        return buildApiResponseHelper(true, 'تم تاكيد الشحنة بنجاح');
    }
}
