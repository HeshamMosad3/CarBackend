<?php

namespace App\Http\Controllers\API\V1\User\Requests;

use App\Enums\Notifications\NotificationCategoryEnum;
use App\Exceptions\CustomResponseException;
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

    public function __construct(protected RequestService $requestService, protected OTOServiceUtils $otoServiceUtils) {}

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
            $shippingRequest = ShippingRequest::where('request_id', $request->requestId)->where('response_id', $request->responseId)->latest()->first();

            if (!$shippingRequest) {
                return buildApiResponseHelper(false, 'لا يوجد شحنة لهذا الطلب');
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

    public function confirmPriceShippingRequest(ConfirmPriceShippingRequest $request)
    {
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
