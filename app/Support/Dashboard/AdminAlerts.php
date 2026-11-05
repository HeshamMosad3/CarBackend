<?php

namespace App\Support\Dashboard;

use App\Enums\AdminAlertType;
use App\Events\DashboardUpdated;
use App\Jobs\SendAdminAlertEmailJob;
use App\Models\AdminAlert;
use App\Models\AdminNotificationEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one place the rest of the app talks to the admin dashboard.
 *
 *  - touch():  "this data changed", so every open dashboard refreshes what it shows.
 *  - raise():  "this needs the admin" — also stored, announced with a browser notification and sound,
 *              and emailed to the recipients who chose that kind of alert.
 *
 * Neither ever throws: an unreachable websocket server or mail server must not break the request that
 * caused the change.
 */
class AdminAlerts
{
    /** The private websocket channel only logged-in admins may join (see routes/channels.php). */
    public const CHANNEL = 'admin.dashboard';

    /** @var array<string,float> last time each entity/action was announced in this process */
    private static array $recent = [];

    public static function touch(string $entity, string $action, $id = null): void
    {
        // A request often saves several rows of the same kind in a row; one announcement is enough.
        $key = $entity . '|' . $action;
        $now = microtime(true);
        if (isset(self::$recent[$key]) && $now - self::$recent[$key] < 0.25) {
            return;
        }
        self::$recent[$key] = $now;

        self::afterCommit(fn () => self::broadcast($entity, $action, $id, null));
    }

    public static function raise(AdminAlertType $type, string $title, string $body, string $url, array $data = [], string $entity = 'alert'): ?AdminAlert
    {
        try {
            $alert = AdminAlert::create([
                'type' => $type->value,
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'tone' => $type->tone(),
                'data' => $data ?: null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        self::afterCommit(function () use ($alert, $entity) {
            self::broadcast($entity, 'alert', $alert->id, $alert);
            self::email($alert);
        });

        return $alert;
    }

    private static function broadcast(string $entity, string $action, $id, ?AdminAlert $alert): void
    {
        try {
            DashboardUpdated::dispatch($entity, $action, $id, $alert?->toClient(), AdminAttention::fresh());
        } catch (Throwable $e) {
            Log::warning('[Dashboard] live update not sent: ' . $e->getMessage());
        }
    }

    private static function email(AdminAlert $alert): void
    {
        try {
            $type = $alert->alertType();
            if (!$type) {
                return;
            }

            $anyone = AdminNotificationEmail::where('is_active', true)->get()->contains(fn ($r) => $r->wants($type));
            if ($anyone) {
                SendAdminAlertEmailJob::dispatch($alert->id);
            }
        } catch (Throwable $e) {
            Log::warning('[Dashboard] alert email not queued: ' . $e->getMessage());
        }
    }

    /** Runs after the surrounding database transaction commits, so the dashboard re-reads committed data. */
    private static function afterCommit(callable $callback): void
    {
        try {
            DB::afterCommit($callback);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
