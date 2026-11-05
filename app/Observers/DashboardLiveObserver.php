<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Complaint;
use App\Models\RequestCustomer;
use App\Models\RequestResponse;
use App\Models\ShippingRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Dashboard\AdminAlerts;
use Illuminate\Database\Eloquent\Model;

/**
 * Tells every open admin dashboard when something it shows has changed, so lists, counters and
 * detail pages update by themselves. It only announces; it never changes data.
 *
 * Note: Eloquent events don't fire for bulk query updates (Model::where(...)->update()). The few
 * places that do that raise their own alert, and the dashboard also re-checks every 30 seconds.
 */
class DashboardLiveObserver
{
    private const ENTITIES = [
        RequestCustomer::class => 'request',
        RequestResponse::class => 'response',
        ShippingRequest::class => 'shipping',
        Complaint::class => 'complaint',
        Vendor::class => 'vendor',
        User::class => 'user',
        Category::class => 'category',
    ];

    public static function register(): void
    {
        foreach (array_keys(self::ENTITIES) as $model) {
            $model::observe(self::class);
        }
    }

    public function created(Model $model): void
    {
        $this->announce($model, 'created');
    }

    public function updated(Model $model): void
    {
        // Users are saved for many small reasons (tokens, devices...): only what the dashboard shows matters.
        if ($model instanceof User && !$model->wasChanged(['status', 'name', 'phone', 'logo'])) {
            return;
        }

        $this->announce($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->announce($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->announce($model, 'restored');
    }

    private function announce(Model $model, string $action): void
    {
        $entity = self::ENTITIES[get_class($model)] ?? null;
        if ($entity) {
            AdminAlerts::touch($entity, $action, $model->getKey());
        }
    }
}
