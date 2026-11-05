<?php

namespace App\Support\Dashboard;

use App\Enums\AdminAlertType;
use App\Models\AdminAlert;

/**
 * What the dashboard's JavaScript needs to go live: where the websocket is, how to authorize on it,
 * and the starting point (counts, last alert) to compare changes against.
 */
class LiveConfig
{
    public static function make(?string $scope = null): array
    {
        $reverb = config('broadcasting.connections.reverb', []);
        $options = $reverb['options'] ?? [];
        $live = config('services.dashboard_live', []);

        // The browser may reach the websocket server at a different address than this server does
        // (e.g. an internal host), so DASHBOARD_REVERB_* can override REVERB_*.
        $host = $live['host'] ?? null ?: ($options['host'] ?? '');
        $port = (int) (($live['port'] ?? null) ?: ($options['port'] ?? 443));
        $scheme = ($live['scheme'] ?? null) ?: ($options['scheme'] ?? 'https');
        $attention = AdminAttention::counts();

        return [
            // Without a websocket server the dashboard still works: it just checks every few seconds.
            'realtime' => !empty($reverb['key']) && $host !== '',
            'key' => $reverb['key'] ?? '',
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'authEndpoint' => route('dashboard.broadcasting.auth'),
            'pulseUrl' => route('dashboard.live.pulse'),
            'chartUrl' => route('dashboard.live.chart'),
            'settingsUrl' => route('dashboard.settings.notification-emails.index'),
            'token' => AdminAttention::fingerprint($attention),
            'lastAlertId' => (int) AdminAlert::max('id'),
            'counts' => $attention,
            'types' => AdminAlertType::options(),
            'scope' => $scope ?: '',
            'icon' => asset('assets/dashboard/images/logo.png'),
            'appName' => config('app.name'),
        ];
    }
}
