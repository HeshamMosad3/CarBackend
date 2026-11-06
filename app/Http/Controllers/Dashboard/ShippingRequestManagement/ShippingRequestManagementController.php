<?php

namespace App\Http\Controllers\Dashboard\ShippingRequestManagement;

use App\Enums\StatusShippingRequestEnum;
use App\Exceptions\CustomResponseException;
use App\Http\Controllers\Controller;
use App\Http\Services\Dashboard\ShippingRequestManagement\ShippingRequestManagementService;
use App\Http\Services\Shared\ShippingWorkflowService;
use App\Models\ShippingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShippingRequestManagementController extends Controller
{
    /** Waybills are the carrier's label as a photo/scan or a PDF; the type is checked from the file's content. */
    private const WAYBILL_RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];

    public function __construct(
        protected ShippingRequestManagementService $service,
        protected ShippingWorkflowService $workflow,
    ) {}

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return $this->service->index($request);
        }
        return view('dashboard.shipping-request-management.index', [
            'groupCounts' => $this->service->groupCounts(),
        ]);
    }

    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:shipping_requests,id',
            'status' => ['required', Rule::enum(StatusShippingRequestEnum::class)],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $shippingRequest = ShippingRequest::find($request->id);

        // This dropdown only drives the legacy three-step lifecycle. Shipments on the new flow
        // change status through ShippingWorkflowService, which enforces the allowed transitions.
        $requested = StatusShippingRequestEnum::from($request->status);
        if (!$requested->isLegacy() || !$shippingRequest->status->isLegacy()) {
            return buildApiResponseHelper(false, 'لا يمكن تعديل حالة هذا الشحن من هنا');
        }

        $previousStatus = $shippingRequest->status;
        $shippingRequest->status = $request->status;

        if (! $shippingRequest->save())
            return buildApiResponseHelper(false, 'لم يتم التعديل بنجاح');

        if ($previousStatus !== $requested) {
            $this->workflow->announceLegacyStatus($shippingRequest);
        }

        $message = '';

        if ($request->status == StatusShippingRequestEnum::Pending->value) {
            $message = 'الشحن قيد الإنتظار';
        } else if ($request->status == StatusShippingRequestEnum::InProgress->value) {
            $message = 'الشحن قيد التنفيذ';
        } else if ($request->status == StatusShippingRequestEnum::Completed->value) {
            $message = 'الشحن مكتمل';
        }

        return buildApiResponseHelper(true, $message);
    }

    public function show(Request $request, $id)
    {

        $result = $this->service->show($id);
        $shippingRequest = $result['shippingRequest'] ?? null;
        $deliveryCompanies = $result['deliveryCompanies'] ?? [];
        $parties = $result['parties'] ?? null;
        $needsRefund = $shippingRequest ? $this->workflow->needsRefund($shippingRequest) : false;

        return view('dashboard.shipping-request-management.show', compact('shippingRequest', 'deliveryCompanies', 'parties', 'needsRefund'));
    }

    /** Moves a new-flow shipment to its next status. The rules live in ShippingWorkflowService. */
    public function transition(Request $request, $id)
    {
        $targets = [
            StatusShippingRequestEnum::SearchingCarrier,
            StatusShippingRequestEnum::AwaitingPayment,
            StatusShippingRequestEnum::BookingConfirmed,
            StatusShippingRequestEnum::PickedUp,
            StatusShippingRequestEnum::InTransit,
            StatusShippingRequestEnum::Delivered,
            StatusShippingRequestEnum::Unavailable,
            StatusShippingRequestEnum::Canceled,
        ];

        $validator = Validator::make($request->all(), [
            'to' => ['required', Rule::in(array_map(fn ($s) => $s->value, $targets))],
            'shipping_cost' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'carrier_name' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'pickup_details' => ['nullable', 'string', 'max:2000'],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
            'pickup_method' => ['nullable', Rule::in(\App\Support\Shipping\PickupMethod::ALL)],
            'oto_delivery_option_id' => ['nullable', 'string', 'max:40'],
            'carrier_pickup_support' => ['nullable', 'string', 'max:30'],
            'waybill' => self::WAYBILL_RULES,
        ]);

        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        $shippingRequest = ShippingRequest::findOrFail($id);
        $to = StatusShippingRequestEnum::from($request->input('to'));

        // Only the columns that belong to the chosen step are accepted.
        $allowed = match ($to) {
            // The carrier chosen while pricing is remembered, so confirming later is already filled in.
            StatusShippingRequestEnum::AwaitingPayment => ['shipping_cost', 'carrier_name', 'oto_delivery_option_id', 'pickup_method', 'carrier_pickup_support'],
            StatusShippingRequestEnum::BookingConfirmed => ['carrier_name', 'tracking_number', 'pickup_details', 'pickup_method', 'oto_delivery_option_id', 'carrier_pickup_support'],
            StatusShippingRequestEnum::Unavailable, StatusShippingRequestEnum::Canceled => ['cancel_reason'],
            default => [],
        };
        $attributes = array_filter($request->only($allowed), fn ($v) => $v !== null && $v !== '');

        try {
            $shippingRequest = $this->workflow->transition($shippingRequest, $to, $attributes);

            // The waybill can come with the booking itself.
            if ($to === StatusShippingRequestEnum::BookingConfirmed && $request->hasFile('waybill')) {
                $this->workflow->attachWaybill($shippingRequest, $request->file('waybill'));
            }
        } catch (CustomResponseException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', StatusShippingRequestEnum::trans($to->value));
    }

    /** Upload or replace the carrier's waybill (image or PDF) after the booking. */
    public function uploadWaybill(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['waybill' => array_merge(['required'], self::WAYBILL_RULES)]);
        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        try {
            $this->workflow->attachWaybill(ShippingRequest::findOrFail($id), $request->file('waybill'));
        } catch (CustomResponseException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم رفع بوليصة الشحن');
    }

    /** Lets the admin open the uploaded waybill. */
    public function showWaybill($id)
    {
        $shippingRequest = ShippingRequest::findOrFail($id);
        $path = $shippingRequest->waybill_file
            ? Storage::disk('local')->path(ShippingRequest::WAYBILL_DIR . '/' . $shippingRequest->waybill_file)
            : null;

        abort_unless($path && is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => mime_content_type($path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function refund(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['refund_note' => ['nullable', 'string', 'max:255']]);
        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        try {
            $this->workflow->markRefunded(ShippingRequest::findOrFail($id), $request->input('refund_note'));
        } catch (CustomResponseException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تسجيل رد المبلغ');
    }

    public function saveNotes(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['admin_notes' => ['nullable', 'string', 'max:5000']]);
        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        ShippingRequest::findOrFail($id)->update(['admin_notes' => $request->input('admin_notes')]);

        return back()->with('success', 'تم حفظ الملاحظات');
    }

    public function createOrderShippingRequest(Request $request)
    {
        try {
            $this->service->createOrderShippingRequest($request);
        } catch (CustomResponseException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم إنشاء طلب الشحن لدى OTO بنجاح');
    }

    public function delete(Request $request, $id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer|exists:shipping_requests,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $shippingRequest = ShippingRequest::find($id);

        return $shippingRequest->delete()
            ? buildApiResponseHelper(true, 'تم الحذف  بنجاح')
            : buildApiResponseHelper(false, 'لم يتم الحذف  بنجاح');
    }
}
