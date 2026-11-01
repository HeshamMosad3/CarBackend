<?php

namespace App\Http\Repositories\Dashboard\ShippingRequestManagement;

use App\Models\ShippingRequest;
use App\Traits\HandlesDatatablesTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShippingRequestManagementRepository
{

    use HandlesDatatablesTrait;

    public function index(Request $request, $searchValue)
    {
        $query = ShippingRequest::select(
            'shipping_requests.id',
            'shipping_requests.request_id',
            'shipping_requests.response_id',
            'shipping_requests.city_origin_vendor',
            'shipping_requests.city_origin_dimensions',
            'shipping_requests.phone_origin_dimensions',
            'shipping_requests.fee_cheapest_shipping',
            'shipping_requests.amount_rate_app',
            'shipping_requests.is_user_confirmed',
            'shipping_requests.shipping_cost',
            'shipping_requests.payment_status',
            'shipping_requests.status',
            'shipping_requests.created_at as shipping_request_date',
        )
            ->confirmShippingFilter($request->input('confirmShippingFilter'))
            ->orderBy('shipping_requests.created_at', 'desc');

        return $this->paginateRecordsForDatatables($request, $query);
    }

    public function recordsCountShippingRequestWithFilter($searchValue)
    {
        return ShippingRequest::select('count(*) as allcount')->searchValueFilter($searchValue)->count();
    }

    public function show($id)
    {
        return ShippingRequest::where('id', $id)->first();
    }

    /** Name and phone numbers of the customer and the vendor behind a shipment. */
    public function getParties(ShippingRequest $shippingRequest): array
    {
        $customer = DB::table('request_customers')
            ->join('users', 'users.id', '=', 'request_customers.user_id')
            ->where('request_customers.id', $shippingRequest->request_id)
            ->first(['users.name', 'users.phone']);

        $vendor = DB::table('request_responses')
            ->join('vendors', 'vendors.id', '=', 'request_responses.vendor_id')
            ->join('users', 'users.id', '=', 'vendors.user_id')
            ->where('request_responses.id', $shippingRequest->response_id)
            ->first(['vendors.company_name_ar', 'vendors.phone_contact', 'users.name', 'users.phone']);

        return ['customer' => $customer, 'vendor' => $vendor];
    }

    public function getShippingRequestDetailById($id)
    {
        return ShippingRequest::select(
            'shipping_requests.*',
            'request_user.name as customer_name',
            'vendor_user.name as company_sender_name',
            'vendors.user_id as vendor_user_id',
            'request_responses.price as response_price'
        )
            ->leftJoin('request_customers', 'request_customers.id', '=', 'shipping_requests.request_id')
            ->leftJoin('users as request_user', 'request_user.id', '=', 'request_customers.user_id')
            ->leftJoin('request_responses', 'request_responses.id', '=', 'shipping_requests.response_id')
            ->leftJoin('vendors', 'vendors.id', '=', 'request_responses.vendor_id')
            ->leftJoin('users as vendor_user', 'vendor_user.id', '=', 'vendors.user_id')
            ->where('shipping_requests.id', $id)
            ->first();
    }
}
