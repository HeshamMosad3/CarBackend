<?php

namespace App\Http\Services\Shared;

use App\Enums\AdminAlertType;
use App\Enums\ComplaintSubjectEnum;
use App\Enums\ComplaintUserTypeEnum;
use App\Http\Repositories\Shared\ComplaintRepository;
use App\Support\Dashboard\AdminAlerts;
use Illuminate\Http\Request;

class ComplaintService
{
    public function __construct(protected ComplaintRepository $complaintRepository) {}

    public function complaintVendorService(Request $request)
    {
        // إبلاغ عن إساءة
        $complaint = $this->complaintRepository->create([
            'user_type' => ComplaintUserTypeEnum::User->value,
            'user_id' => getCurrUserIdHelper(),
            'subject' => ComplaintSubjectEnum::VendorService->value,
            'request_id' => $request->requestId,
            'title' => 'بلاغ عن الطلب (' . $request->requestId . ') ' . ' - الرد رقم (' . $request->responseId . ')',
            'description' => $request->description,
        ]);

        AdminAlerts::raise(
            AdminAlertType::ComplaintNew,
            'شكوى جديدة',
            $complaint->title . ($complaint->description ? ' — ' . mb_strimwidth((string) $complaint->description, 0, 140, '…') : ''),
            route('dashboard.complaint-management.complaints', ['status' => 'new'], false),
            ['complaint_id' => $complaint->id],
            'complaint',
        );

        return $complaint;
    }
}
