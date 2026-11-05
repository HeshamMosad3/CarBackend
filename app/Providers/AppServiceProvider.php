<?php

namespace App\Providers;

use App\Enums\user\UserRoleEnum;
use App\Models\BrandCar;
use App\Models\Category;
use App\Models\CategoryHasBrandField;
use App\Models\City;
use App\Models\CustomField;
use App\Observers\BrandCarObserver;
use App\Observers\CategoryHasBrandFieldObserver;
use App\Observers\CategoryObserver;
use App\Observers\CityObserver;
use App\Observers\CustomFieldObserver;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Swap this binding for the real gateway implementation when one is added.
        // SHIPPING_PAYMENT_DEMO=true makes every shipping payment succeed without charging anything.
        $this->app->bind(
            \App\Contracts\ShippingPaymentGateway::class,
            filter_var(env('SHIPPING_PAYMENT_DEMO', false), FILTER_VALIDATE_BOOLEAN)
                ? \App\Services\Payments\DemoShippingPaymentGateway::class
                : \App\Services\Payments\NullShippingPaymentGateway::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        City::observe(CityObserver::class);
        BrandCar::observe(BrandCarObserver::class);
        Category::observe(CategoryObserver::class);
        CategoryHasBrandField::observe(CategoryHasBrandFieldObserver::class);
        CustomField::observe(CustomFieldObserver::class);
        // Live updates for the admin dashboard (lists, counters and detail pages refresh by themselves).
        \App\Observers\DashboardLiveObserver::register();

        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::before(function ($user, $ability) {
            return $user->hasRole(UserRoleEnum::Super_Admin->value) ? true : null;
        });

        if ($this->app->environment('production') || env('FORCE_HTTPS', false)) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Every DatabaseNotification write (any ->notify() call, from any
        // notification class) gets its indexed category/target_id/badge_category
        // columns populated from the `data` blob automatically. This is what
        // NotificationCountsService and the badge endpoints query — see the
        // add_category_columns_to_notifications_table migration.
        //
        // (The realtime NotificationBadgeUpdated broadcast itself now happens
        // in NotificationDispatcherService, not here — that seam also covers
        // chat pushes, which write no DatabaseNotification row at all.)
        DatabaseNotification::saving(function (DatabaseNotification $notification) {
            $data = $notification->data ?? [];
            $notification->category = $data['category'] ?? null;
            $notification->target_id = isset($data['target_id']) && $data['target_id'] !== null
                ? (string) $data['target_id']
                : null;
            $notification->badge_category = $data['badge_category'] ?? null;
        });
    }
}