<?php

namespace App\Http\Controllers\API\V1\Vendor;

use App\Enums\Notifications\NotificationCategoryEnum;
use App\Exceptions\CustomResponseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\ResponseRequest\SendResponseRequest;
use App\Http\Services\Vendor\ResponseRequestService;
use App\Models\RequestResponse;
use App\Traits\NotificationsTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResponseRequestController extends Controller
{
    use NotificationsTrait;

    public function __construct(protected ResponseRequestService $responseRequestService) {}

    public function getMyResponseRequests(Request $request)
    {
        $result = $this->responseRequestService->getMyResponseRequests();

        return buildApiResponseHelper(true, 'تم التحميل بنجاح', resultApiPaginationHelper($result));
    }

    public function sendResponseRequest(SendResponseRequest $request)
    {
        DB::beginTransaction();
        try {
            $createResponseRequest = $this->responseRequestService->sendResponseRequest($request);

            $requestResponse = RequestResponse::joinRequestCustomer()
                ->leftJoinVendor()
                ->where('request_responses.id', $createResponseRequest->id)
                ->select(
                    'vendors.company_name_ar',
                    'request_customers.user_id',
                )
                ->first();

            DB::commit();

            try {
                $this->notifyByID(
                    $requestResponse->user_id,
                    'رد جديد',
                    'تم الرد على طلبك من ' . ' ' . $requestResponse->company_name_ar,
                    true,
                    NotificationCategoryEnum::VendorResponse,
                    $request->requestId
                );
            } catch (\Throwable $notifEx) {
                \Illuminate\Support\Facades\Log::warning('Failed to dispatch notification on sendResponseRequest: ' . $notifEx->getMessage());
            }

            return buildApiResponseHelper(true, 'تم ارسال الرد بنجاح');
        } catch (\Exception $e) {
            report($e);
            throw new CustomResponseException('حدث خطاء اثناء ارسال الرد ... الرجاء المحاولة مرة اخرى');
        }
    }

    public function detailsResponseRequests(Request $request, $responseId)
    {
        return $this->responseRequestService->detailsResponseRequests($request, $responseId);
    }
}
