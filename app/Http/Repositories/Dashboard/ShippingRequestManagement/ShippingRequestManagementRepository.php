<?php

namespace App\Http\Repositories\Dashboard\ShippingRequestManagement;

use App\Enums\StatusShippingRequestEnum as Status;
use App\Models\ShippingRequest;
use App\Support\Dashboard\AdminAttention;
use App\Traits\HandlesDatatablesTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShippingRequestManagementRepository
{

    use HandlesDatatablesTrait;

    /** The list tabs. Each maps to a set of statuses (or a rule from AdminAttention). */
    public const GROUPS = ['action', 'refund', 'waiting', 'active', 'done', 'closed'];

    public function index(Request $request, $searchValue)
    {
        $query = $this->filteredQuery($request, $searchValue)->select(
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
            'shipping_requests.carrier_name',
            'shipping_requests.tracking_number',
        );

        return $this->paginateRecordsForDatatables($request, $query);
    }

    public function recordsCountShippingRequestWithFilter($searchValue, ?Request $request = null)
    {
        return $this->filteredQuery($request, $searchValue)->count();
    }

    /** Rows and filtered count share this, so the pager always matches the rows. */
    private function filteredQuery(?Request $request, $searchValue)
    {
        $group = $request ? $request->input('group') : null;

        return $this->applyGroup(ShippingRequest::query(), in_array($group, self::GROUPS, true) ? $group : null)
            ->when($searchValue, function ($q, $value) {
                $q->whereAny([
                    'shipping_requests.id',
                    'shipping_requests.request_id',
                    'shipping_requests.response_id',
                    'shipping_requests.city_origin_vendor',
                    'shipping_requests.city_origin_dimensions',
                    'shipping_requests.phone_origin_dimensions',
                    'shipping_requests.tracking_number',
                    'shipping_requests.oto_id',
                    'shipping_requests.carrier_name',
                ], 'like', '%' . $value . '%');
            });
    }

    public function applyGroup($query, ?string $group)
    {
        $statuses = fn (array $list) => array_map(fn (Status $s) => $s->value, $list);

        return match ($group) {
            'action' => AdminAttention::shippingNeedsAction($query),
            'refund' => AdminAttention::shippingNeedsRefund($query),
            'waiting' => $query->where(function ($q) use ($statuses) {
                $q->whereIn('shipping_requests.status', $statuses([Status::AwaitingCustomerApproval, Status::AwaitingCustomerData, Status::AwaitingPayment]))
                    ->orWhere(fn ($q) => $q->where('shipping_requests.status', Status::Pending->value)->where('shipping_requests.is_user_confirmed', false));
            }),
            'active' => $query->whereIn('shipping_requests.status', $statuses([Status::BookingConfirmed, Status::PickedUp, Status::InTransit, Status::InProgress])),
            'done' => $query->whereIn('shipping_requests.status', $statuses([Status::Delivered, Status::Completed])),
            'closed' => $query->whereIn('shipping_requests.status', $statuses([Status::Canceled, Status::Unavailable])),
            default => $query,
        };
    }

    public function groupCounts(): array
    {
        $counts = ['' => ShippingRequest::count()];
        foreach (self::GROUPS as $group) {
            $counts[$group] = $this->applyGroup(ShippingRequest::query(), $group)->count();
        }

        return $counts;
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
