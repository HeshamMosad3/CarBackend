<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\StatusUserEnum;
use App\Enums\user\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\RequestCustomer;
use App\Models\RequestResponse;
use App\Models\ShippingRequest;
use App\Models\User;
use App\Support\Dashboard\AdminAttention;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const CHART_DAYS = 14;

    public function index()
    {
        $attention = AdminAttention::counts();

        $stats = [
            'requests' => RequestCustomer::count(),
            'openRequests' => RequestCustomer::where('status', 'open')->count(),
            'requestsToday' => RequestCustomer::whereDate('created_at', today())->count(),
            'responses' => RequestResponse::count(),
            'vendors' => User::role(UserRoleEnum::Vendor->value)->where('status', StatusUserEnum::Active->value)->count(),
            'customers' => User::role(UserRoleEnum::User->value)->count(),
            'customersThisWeek' => User::role(UserRoleEnum::User->value)->where('created_at', '>=', now()->subDays(7))->count(),
            'shipments' => ShippingRequest::count(),
            'categories' => Category::count(),
        ];

        $recentRequests = RequestCustomer::leftJoinCategory()
            ->leftJoinCity()
            ->select(
                'request_customers.id',
                'request_customers.status',
                'request_customers.created_at',
                'categories.cat_name_ar',
                'cities.city_name_ar',
                DB::raw('(SELECT COUNT(id) FROM request_responses WHERE request_responses.request_id = request_customers.id) as responses_count')
            )
            ->orderByDesc('request_customers.id')
            ->limit(6)
            ->get();

        $shipmentsToHandle = AdminAttention::shippingNeedsAction(ShippingRequest::query())
            ->orWhere(fn ($q) => AdminAttention::shippingNeedsRefund($q))
            ->orderBy('updated_at')
            ->limit(6)
            ->get(['id', 'status', 'payment_status', 'city_origin_vendor', 'city_origin_dimensions', 'updated_at']);

        $pendingVendors = User::joinVendors()
            ->role(UserRoleEnum::Vendor->value)
            ->where('users.status', StatusUserEnum::Pending->value)
            ->orderBy('users.created_at')
            ->limit(5)
            ->get(['users.id', 'users.created_at', 'vendors.company_name_ar', 'vendors.commercial_record']);

        return view('dashboard.dashboard', [
            'attention' => $attention,
            'stats' => $stats,
            'chart' => $this->activityChart(),
            'recentRequests' => $recentRequests,
            'shipmentsToHandle' => $shipmentsToHandle,
            'pendingVendors' => $pendingVendors,
        ]);
    }

    /** Daily requests, offers and shipments over the last two weeks. */
    private function activityChart(): array
    {
        $from = today()->subDays(self::CHART_DAYS - 1);
        // Through the models so soft-deleted rows stay out of the counts.
        $perDay = fn (string $model) => $this->safe(fn () => $model::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day'));

        $requests = $perDay(RequestCustomer::class);
        $responses = $perDay(RequestResponse::class);
        $shipments = $perDay(ShippingRequest::class);

        $chart = ['labels' => [], 'requests' => [], 'responses' => [], 'shipments' => []];
        foreach (CarbonPeriod::create($from, today()) as $day) {
            $key = $day->toDateString();
            $chart['labels'][] = $day->translatedFormat('d M');
            $chart['requests'][] = (int) ($requests[$key] ?? 0);
            $chart['responses'][] = (int) ($responses[$key] ?? 0);
            $chart['shipments'][] = (int) ($shipments[$key] ?? 0);
        }

        return $chart;
    }

    private function safe(callable $query)
    {
        try {
            return $query();
        } catch (\Throwable $e) {
            report($e);
            return collect();
        }
    }
}
