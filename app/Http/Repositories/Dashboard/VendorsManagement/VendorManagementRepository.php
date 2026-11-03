<?php

namespace App\Http\Repositories\Dashboard\VendorsManagement;

use App\Enums\StatusUserEnum;
use App\Enums\user\UserRoleEnum;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Traits\HandlesDatatablesTrait;
use Illuminate\Http\Request;

class VendorManagementRepository
{
    use HandlesDatatablesTrait;

    public function index(Request $request, $searchValue)
    {
        $query = User::joinVendors()
            ->role(UserRoleEnum::Vendor->value)
            ->searchValueFilter($searchValue)
            ->where('users.status', '!=', StatusUserEnum::Pending->value)
            ->when($this->statusFilter($request), fn ($q, $status) => $q->where('users.status', $status))
            ->select(
                'users.id',
                'vendors.company_name_ar',
                'vendors.commercial_record',
                'users.phone',
                'users.logo',
                'users.status',
                'users.created_at as member_since',
            );

        return $this->paginateRecordsForDatatables($request, $query);
    }

    public function recordsCountVendors()
    {
        return $this->queryRecordsCountVendors()
            ->count();
    }

    public function recordsCountVendorsWithFilter($searchValue, ?Request $request = null)
    {
        return $this->queryRecordsCountVendors()
            ->searchValueFilter($searchValue)
            ->when($request ? $this->statusFilter($request) : null, fn ($q, $status) => $q->where('status', $status))
            ->count();
    }

    /** Optional ?status= filter from the vendors list; ignores anything that isn't a real status. */
    private function statusFilter(Request $request): ?string
    {
        $status = StatusUserEnum::tryFrom((string) $request->input('status'));

        return $status && $status !== StatusUserEnum::Pending ? $status->value : null;
    }

    private function queryRecordsCountVendors()
    {
        return User::select('count(*) as allcount')
            ->role(UserRoleEnum::Vendor->value)
            ->where('status', '!=', StatusUserEnum::Pending->value);
    }

    public function getVendorsWithoutPendingByUserId($userId)
    {
        return User::joinVendors()
            ->role(UserRoleEnum::Vendor->value)
            ->where('users.status', '!=', StatusUserEnum::Pending->value)
            ->where('users.id', $userId)
            ->select(
                'users.phone',
                'users.logo',
                'users.created_at as member_since',
                'vendors.*',
                'vendors.id as vendor_id',
                'users.status as account_status',
            )
            ->first();
    }

    public function getVendorsByUserId($userId, $status)
    {
        return User::joinVendors()
            ->role(UserRoleEnum::Vendor->value)
            // ->where('users.status', $status)
            ->where('users.id', $userId)
            ->select(
                'users.phone',
                'users.logo',
                'users.created_at as member_since',
                'vendors.*',
                'vendors.id as vendor_id',
                'users.status as account_status',
            )
            ->first();
    }

    public function getVendorDocumentByVendorId($vendorId)
    {
        return VendorDocument::where('vendor_id', $vendorId)
            ->first(['file_path'])?->file_path;
    }

    public function getVendorCities($vendorId)
    {
        return Vendor::join('vendor_cities', 'vendor_cities.vendor_id', '=', 'vendors.id')
            ->leftJoin('cities', 'cities.id', '=', 'vendor_cities.city_id')
            ->where('vendors.id', $vendorId)
            ->select(
                'cities.city_name_ar'
            )
            ->get();
    }

    public function getVendorCategories($vendorId)
    {
        return Vendor::join('vendor_specialties', 'vendor_specialties.vendor_id', '=', 'vendors.id')
            ->leftJoin('categories', 'categories.id', '=', 'vendor_specialties.category_id')
            ->where('vendors.id', $vendorId)
            ->select(
                'categories.cat_name_ar'
            )
            ->get();
    }

    public function updateVendorStatus($userId, $status)
    {
        return User::where('id', $userId)->update(['status' => $status]);
    }

    public function deleteVendor($userId)
    {
        $deleted = User::where('id', $userId)->delete();
        if ($deleted)
            Vendor::where('user_id', $userId)->delete();
        return $deleted;
    }
}
