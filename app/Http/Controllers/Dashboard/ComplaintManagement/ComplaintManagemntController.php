<?php

namespace App\Http\Controllers\Dashboard\ComplaintManagement;

use App\Enums\ComplaintStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Services\Dashboard\ComplaintManagemnt\ComplaintManagemntService;
use App\Models\Complaint;
use App\Support\Dashboard\StatusPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ComplaintManagemntController extends Controller
{
    public function __construct(protected ComplaintManagemntService $service) {}

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return $this->service->index($request);
        }

        return view('dashboard.complaint-management.index', [
            'statusCounts' => $this->service->statusCounts(),
        ]);
    }

    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:complaints,id',
            'status' => ['required', Rule::enum(ComplaintStatusEnum::class)],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $complaint = Complaint::findOrFail($request->id);
        $complaint->status = $request->status;

        if (!$complaint->save()) {
            return buildApiResponseHelper(false, 'لم يتم التعديل بنجاح');
        }

        [$label] = StatusPresenter::get('complaint', $request->status);

        return buildApiResponseHelper(true, 'حالة الشكوى الآن: ' . $label);
    }
}
