<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Repositories\Dashboard\RequestsManagement\RequestsManagementRepository;
use App\Http\Repositories\Dashboard\ShippingRequestManagement\ShippingRequestManagementRepository;
use App\Http\Services\Dashboard\ComplaintManagemnt\ComplaintManagemntService;
use App\Models\AdminAlert;
use App\Support\Dashboard\AdminAttention;
use Illuminate\Http\Request;

/**
 * What the dashboard asks for to stay current: on a websocket change, as the 30-second safety net,
 * when the tab comes back to the front, and to catch up on alerts that happened while it was closed.
 */
class DashboardLiveController extends Controller
{
    /** Alerts older than this are history, not news: they are listed in the bell but never popped up. */
    private const FRESH_ALERT_MINUTES = 10;

    public function pulse(Request $request)
    {
        $attention = AdminAttention::fresh();
        $after = (int) $request->query('after', 0);

        $newAlerts = AdminAlert::where('id', '>', $after)
            ->where('created_at', '>=', now()->subMinutes(self::FRESH_ALERT_MINUTES))
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map->toClient()
            ->values();

        return response()->json([
            'token' => AdminAttention::fingerprint($attention),
            'counts' => $attention,
            'last_alert_id' => (int) AdminAlert::max('id'),
            'new_alerts' => $newAlerts,
            // The bell's dropdown, ready to drop in (it has its own history of recent alerts).
            'menu' => view('dashboard.included.attention-menu', [
                'attention' => $attention,
                'recentAlerts' => AdminAlert::latest('id')->limit(8)->get(),
            ])->render(),
            // The numbers on the current page's filter tabs, when it has any.
            'chips' => $this->chipCounts((string) $request->query('scope')),
        ]);
    }

    /** The activity chart on the home page. */
    public function chart(DashboardController $home)
    {
        return response()->json($home->chartData());
    }

    /** @return array<string,int> keyed like the data-count-key on the page's filter tabs */
    private function chipCounts(string $scope): array
    {
        $chips = [];

        if ($scope === 'shipping') {
            foreach (app(ShippingRequestManagementRepository::class)->groupCounts() as $group => $count) {
                $chips['shipping:' . $group] = $count;
            }
        } elseif ($scope === 'requests') {
            $counts = app(RequestsManagementRepository::class)->statusCounts();
            $chips['requests:'] = array_sum($counts);
            foreach ($counts as $status => $count) {
                $chips['requests:' . $status] = $count;
            }
        } elseif ($scope === 'complaints') {
            $counts = app(ComplaintManagemntService::class)->statusCounts();
            $chips['complaints:'] = array_sum($counts);
            foreach ($counts as $status => $count) {
                $chips['complaints:' . $status] = $count;
            }
        }

        return $chips;
    }
}
