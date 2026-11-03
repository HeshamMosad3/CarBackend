<?php

namespace App\Http\Services\Dashboard\VendorsManagement;

use App\Http\Repositories\Dashboard\VendorsManagement\VendorManagementRepository;
use App\Http\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VendorManagementService extends BaseService
{
    public function __construct(protected VendorManagementRepository $repo) {}

    public function index(Request $request)
    {
        $searchValue = $request->input('search.value');

        return $this->repo->formatResponseDataTables(
            draw: $request->input('draw'),
            recordsCount: $this->repo->recordsCountVendors(),
            recordsCountwithFilter: $this->repo->recordsCountVendorsWithFilter($searchValue, $request),
            records: $this->repo->index($request, $searchValue)
        );
    }

    public function getVendorsWithoutPendingByUserId(Request $request, $userId)
    {
        $this->validate(['userId' => $userId], ['userId' => 'required|integer|exists:users,id'], [
            'userId.required' => 'معرف المستخدم مطلوب',
            'userId.exists' => 'معرف المستخدم غير صحيح',
            'userId.integer' => 'معرف المستخدم يجب ان يكون رقم',
        ]);

        $vendor = $this->repo->getVendorsWithoutPendingByUserId($userId);
        $vendorDocument = $this->repo->getVendorDocumentByVendorId($vendor->vendor_id);
        $vendorCities = $this->repo->getVendorCities($vendor->vendor_id);
        $vendorCategories = $this->repo->getVendorCategories($vendor->vendor_id);
        $responsesCount = \App\Models\RequestResponse::where('vendor_id', $vendor->vendor_id)->count();

        return compact('vendor', 'vendorDocument', 'vendorCities', 'vendorCategories', 'responsesCount');
    }

    public function deleteVendor(Request $request, $userId)
    {
        $this->validate(['userId' => $userId], ['userId' => 'required|integer|exists:users,id'], [
            'userId.required' => 'معرف المستخدم مطلوب',
            'userId.exists' => ' المستخدم غير موجود',
            'userId.integer' => 'معرف المستخدم يجب ان يكون رقم',
        ]);

        return $this->repo->deleteVendor($userId);
    }
}
