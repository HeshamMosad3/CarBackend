<?php

namespace App\Http\Controllers\API\V1\User\MyRequests;

use App\Enums\RequestCustomerStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Services\Shared\ShippingWorkflowService;
use App\Http\Services\User\MyRequests\MyRequestUserService;
use App\Models\RequestCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MyRequestUserController extends Controller
{
    public function __construct(
        protected MyRequestUserService $myRequestUserService,
        protected ShippingWorkflowService $shippingWorkflow,
    ) {}

    public function getMyRequest(Request $request)
    {
        $result = $this->myRequestUserService->getMyRequest();

        return buildApiResponseHelper(true, 'تم التحميل بنجاح', resultApiPaginationHelper($result));
    }

    public function getMyRequestById(Request $request, $requestId)
    {
        return $this->myRequestUserService->getMyRequestById($request, $requestId);
    }

    public function getResponsesMyRequest(Request $request, $requestId)
    {
        $result = $this->myRequestUserService->getResponsesMyRequest($request, $requestId);
        return buildApiResponseHelper(true, 'تم التحميل بنجاح', resultApiPaginationHelper($result));
    }

    public function getResponseRequestById(Request $request, $responseId)
    {
        $result = $this->myRequestUserService->getResponseRequestById($request, $responseId);
        return $result
            ? buildApiResponseHelper(true, 'تم التحميل بنجاح', $result)
            : buildApiResponseHelper(false, 'لا يوجد رد ');
    }

    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:request_customers,id',
            'status' => ['required', Rule::enum(RequestCustomerStatusEnum::class)],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $requestCustomer = RequestCustomer::where('id', $request->id)->where('user_id', getCurrUserIdHelper())->first();
        if (!$requestCustomer) {
            return buildApiResponseHelper(false, 'لا يوجد طلب');
        }

        $requestCustomer->status = $request->status;

        if (! $requestCustomer->save())
            return buildApiResponseHelper(false, 'لم يتم التعديل بنجاح');

        if ($request->status == RequestCustomerStatusEnum::Canceled->value) {
            $this->shippingWorkflow->cancelOpenForRequest((int) $requestCustomer->id, 'ألغى الزبون الطلب');
        }

        $message = '';

        if ($request->status == RequestCustomerStatusEnum::Open->value) {
            $message = 'تم تفعيل الطلب بنجاح';
        } else if ($request->status == RequestCustomerStatusEnum::Closed->value) {
            $message = 'تم إغلاق الطلب بنجاح';
        } else if ($request->status == RequestCustomerStatusEnum::Canceled->value) {
            $message = 'تم إلغاء الطلب بنجاح';
        } else if ($request->status == RequestCustomerStatusEnum::Completed->value) {
            $message = 'تم إكتمال الطلب بنجاح';
        }

        return buildApiResponseHelper(true, $message);
    }
}
