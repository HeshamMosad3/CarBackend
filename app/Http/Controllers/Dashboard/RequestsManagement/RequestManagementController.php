<?php

namespace App\Http\Controllers\Dashboard\RequestsManagement;

use App\Enums\Notifications\NotificationCategoryEnum;
use App\Enums\RequestCustomerStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Services\Dashboard\RequestsManagement\RequestsManagementService;
use App\Models\RequestCustomer;
use App\Traits\NotificationsTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RequestManagementController extends Controller
{
    use NotificationsTrait;

    public function __construct(protected RequestsManagementService $service) {}

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return $this->service->index($request);
        }
        return view('dashboard.requests-management.index', ['statusCounts' => $this->service->statusCounts()]);
    }

    public function show($id)
    {
        $requestDetails = $this->service->show($id);
        return view('dashboard.requests-management.show', compact('requestDetails'));
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

        $requestCustomer = RequestCustomer::find($request->id);
        $previousStatus = $requestCustomer->status;
        $requestCustomer->status = $request->status;

        if (! $requestCustomer->save())
            return buildApiResponseHelper(false, 'لم يتم التعديل بنجاح');

        // The customer follows their order from the app, so a status change made here must reach them.
        if ($previousStatus !== $request->status) {
            $this->notifyCustomerOfStatus($requestCustomer);
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

    private function notifyCustomerOfStatus(RequestCustomer $requestCustomer): void
    {
        $order = ' ( ' . $requestCustomer->id . ' )';
        [$title, $body] = match ($requestCustomer->status) {
            RequestCustomerStatusEnum::Open->value => ['تم تفعيل طلبك', 'تم تفعيل طلبك رقم' . $order . ' وأصبح متاحاً للشركات'],
            RequestCustomerStatusEnum::Closed->value => ['تم إغلاق طلبك', 'تم إغلاق طلبك رقم' . $order . ' ولن تصلك عروض جديدة عليه'],
            RequestCustomerStatusEnum::Canceled->value => ['تم إلغاء طلبك', 'تم إلغاء طلبك رقم' . $order],
            RequestCustomerStatusEnum::Completed->value => ['اكتمل طلبك', 'تم تحديد طلبك رقم' . $order . ' كمكتمل'],
            default => [null, null],
        };

        if ($title === null) {
            return;
        }

        try {
            // RequestUpdate opens the request's offers, and counts on the bell rather than a chat/offer badge.
            $this->notifyByID(
                userId: $requestCustomer->user_id,
                title: $title,
                body: $body,
                category: NotificationCategoryEnum::RequestUpdate,
                targetId: $requestCustomer->id,
            );
        } catch (\Throwable $e) {
            report($e); // a push failure must never undo the status change
        }
    }

    public function delete(Request $request, $id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer|exists:request_customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $requestCustomer = RequestCustomer::find($id);

        return $requestCustomer->delete()
            ? buildApiResponseHelper(true, 'تم حذف الطلب بنجاح')
            : buildApiResponseHelper(false, 'لم يتم حذف الطلب بنجاح');
    }
}
