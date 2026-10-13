<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'Laravel is running!';
});

Route::get('/db-test', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $db = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        $usersCount = \App\Models\User::count();
        $rolesCount = \Illuminate\Support\Facades\DB::table('roles')->count();
        return response()->json([
            'status' => 'connected',
            'database' => $db,
            'users_count' => $usersCount,
            'roles_count' => $rolesCount,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 200);
    }
});

Route::get('/setup-demo-accounts', function () {
    try {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

        $customer = \App\Models\User::updateOrCreate(
            ['phone' => '0512345678'],
            [
                'name' => 'عميل تجريبي',
                'status' => 'Active',
            ]
        );
        $customer->syncRoles(['user']);

        $vendorUser = \App\Models\User::updateOrCreate(
            ['phone' => '0555555555'],
            [
                'name' => 'شركة الأمل لقطع الغيار',
                'status' => 'Active',
            ]
        );
        $vendorUser->syncRoles(['vendor']);

        $vendor = \App\Models\Vendor::updateOrCreate(
            ['user_id' => $vendorUser->id],
            [
                'company_name_ar' => 'شركة الأمل لقطع الغيار',
                'company_name_en' => 'Al-Amal Auto Parts',
                'description' => 'متخصصون في قطع الغيار والصيانة',
                'commercial_record' => '1010101010',
                'date_expire_commercial_record' => now()->addYears(5)->toDateString(),
                'phone_contact' => '0555555555',
                'is_hide_phone_contact' => false,
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        foreach (\App\Models\City::all() as $city) {
            \App\Models\VendorCity::firstOrCreate([
                'vendor_id' => $vendor->id,
                'city_id' => $city->id,
            ]);
        }

        foreach (\App\Models\Category::all() as $category) {
            \App\Models\VendorSpecialty::firstOrCreate([
                'vendor_id' => $vendor->id,
                'category_id' => $category->id,
                'is_receive_all_brand_cars' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الحسابات بنجاح',
            'customer' => [
                'type' => 'زبون',
                'phone' => '0512345678',
                'otp' => '11111',
            ],
            'vendor' => [
                'type' => 'شركة / ورشة',
                'phone' => '0555555555',
                'otp' => '11111',
            ],
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

Route::get('/dashboard', [App\Http\Controllers\Dashboard\DashboardController::class, 'index'])
    ->middleware(['auth:admin', 'role:Super-Admin|admin', 'verified'])->name('dashboard');

Route::get('/uploads/{filename}', [App\Http\Controllers\FileController::class, 'getImage']);

Route::prefix('uploads-private')->middleware('auth:admin')->controller(App\Http\Controllers\FileController::class)->group(function () {
    Route::get('/{filename}', 'getSensitiveImage')->name('uploads-private');
});

Route::prefix('/dashboard')->middleware(['auth:admin', 'role:Super-Admin|admin', 'verified'])->group(function () {
    Route::prefix('/categories')->controller(App\Http\Controllers\Dashboard\CategoryController::class)->group(function () {
        Route::get('/', 'index')->name('dashboard.categories.index');
        Route::get('/create', 'create')->name('dashboard.categories.create');
        Route::post('/store', 'store')->name('dashboard.categories.store');
        Route::get('/edit/{id}', 'edit')->name('dashboard.categories.edit');
        Route::post('/update/{id}', 'update')->name('dashboard.categories.update');
        Route::delete('/delete/{id}', 'delete')->name('dashboard.categories.delete');
        Route::post('/update-status', 'updateStatusActiveCategory')->name('dashboard.categories.update-status');
    });
    Route::prefix('/custom-fields-category')->controller(App\Http\Controllers\Dashboard\CustomFieldController::class)->group(function () {
        Route::get('/{categoryId}', 'index')->name('dashboard.custom-fields.index');
        Route::post('/save-custom-field', 'saveCustomField')->name('dashboard.custom-fields.save-custom-field');
        Route::delete('/delete/{id}', 'delete')->name('dashboard.custom-fields.delete');
    });
    Route::prefix('/customers')->controller(App\Http\Controllers\Dashboard\CustomerController::class)->group(function () {
        Route::get('/', 'index')->name('dashboard.customers.index');
        Route::post('/update-status', 'updateStatus')->name('dashboard.customers.update-status');
        Route::delete('/delete/{id}', 'delete')->name('dashboard.customers.delete');
    });
    Route::prefix('/vendors-management/vendors')->controller(App\Http\Controllers\Dashboard\VendorsManagement\VendorManagementController::class)->group(function () {
        Route::get('/', 'index')->name('dashboard.vendors-management.vendors.index');
        Route::get('/show/{userId}', 'show')->name('dashboard.vendors-management.vendors.show');
        Route::delete('/delete/{userId}', 'deleteVendor');
        Route::post('/update-status', 'updateStatus')->name('dashboard.vendors-management.vendors.update-status');
    });
    Route::prefix('/vendors-management/join-requests')->controller(App\Http\Controllers\Dashboard\VendorsManagement\JoinRequestVendorController::class)->group(function () {
        Route::get('/', 'index')->name('dashboard.vendors-management.join-requests.index');
        Route::get('/show/{userId}', 'show')->name('dashboard.vendors-management.join-requests.show');
        Route::post('/active-status', 'activeStatusVendor')->name('dashboard.vendors-management.join-requests.active-status');
        Route::post('/rejected-status/{userId}', 'rejectedStatusVendor')->name('dashboard.vendors-management.join-requests.rejected-status');
        Route::delete('/delete/{userId}', 'deleteVendor');
    });
    Route::prefix('/requests-management')->controller(App\Http\Controllers\Dashboard\RequestsManagement\RequestManagementController::class)->group(function () {
        Route::get('/', 'index')->name('dashboard.requests-management.index');
        Route::get('/show/{id}', 'show')->name('dashboard.requests-management.show');
        Route::post('/update-status', 'updateStatus')->name('dashboard.requests-management.update-status');
        Route::delete('/delete/{id}', 'delete');
    });
    Route::prefix('/response-management')->controller(App\Http\Controllers\Dashboard\RequestResponseManagement\RequestResponseManagementController::class)->group(function () {
        Route::get('/responses/{requestId}', 'index')->name('dashboard.response-management.responses');
    });
    Route::prefix('/shipping-request-management')->controller(App\Http\Controllers\Dashboard\ShippingRequestManagement\ShippingRequestManagementController::class)->group(function () {
        Route::get('/', 'index')->name('dashboard.shipping-request-management.index');
        Route::get('/show/{id}', 'show')->name('dashboard.shipping-request-management.show');
        Route::post('/update-status', 'updateStatus')->name('dashboard.shipping-request-management.update-status');
        Route::post('/create-order-shipping', 'createOrderShippingRequest')->name('dashboard.shipping-request-management.create-order-shipping');
        Route::delete('/delete/{id}', 'delete')->name('dashboard.shipping-request-management.delete');
    });
    Route::prefix('/complaint-management')->controller(App\Http\Controllers\Dashboard\ComplaintManagement\ComplaintManagemntController::class)->group(function () {
        Route::get('/complaints', 'index')->name('dashboard.complaint-management.complaints');
    });

    Route::prefix('logs')->controller(App\Http\Controllers\Dashboard\AdminLogController::class)->group(function () {
        Route::get('/', 'index')->name('dashboard.logs.index');
        Route::post('/clear-logs', 'clearLogs')->name('dashboard.logs.clear-logs');
        Route::get('/download-logs', 'downloadLogs')->name('dashboard.logs.download-logs');
    });

    Route::prefix('settings/notification-emails')->controller(App\Http\Controllers\Dashboard\Settings\NotificationEmailController::class)->group(function () {
        Route::get('/', 'index')->name('dashboard.settings.notification-emails.index');
        Route::post('/', 'store')->name('dashboard.settings.notification-emails.store');
        Route::delete('/{id}', 'destroy')->name('dashboard.settings.notification-emails.destroy');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';

Route::get('/run-migration-now', function() {
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    return 'Migrated!';
});


Route::get('/fix-cache-now', function() {
    \App\Models\CacheStaticDataVersion::updateTimestamp(\App\Enums\EntityNameCacheStaticDataEnum::Categories->value);
    \App\Utils\CacheUtils::forget(\App\Utils\CacheUtils::categoriesCacheStaticDataAppKey());
    return 'Cache fixed!';
});


Route::get('/fix-icon-v2', function() {
    \Illuminate\Support\Facades\DB::table('categories')
        ->where('cat_name_ar', 'LIKE', '%?????%')
        ->orWhere('cat_name_ar', 'LIKE', '%?????%')
        ->update(['cat_icon_path' => 'new-spare-parts-icon-v2.png']);
    \App\Models\CacheStaticDataVersion::updateTimestamp(\App\Enums\EntityNameCacheStaticDataEnum::Categories->value);
    \App\Utils\CacheUtils::forget(\App\Utils\CacheUtils::categoriesCacheStaticDataAppKey());
    return 'Icon V2 fixed!';
});


Route::get('/fix-icon-v3', function() {
    \Illuminate\Support\Facades\DB::table('categories')
        ->where('id', 3)
        ->update(['cat_icon_path' => 'new-spare-parts-icon-v2.png']);
    \App\Models\CacheStaticDataVersion::updateTimestamp(\App\Enums\EntityNameCacheStaticDataEnum::Categories->value);
    \App\Utils\CacheUtils::forget(\App\Utils\CacheUtils::categoriesCacheStaticDataAppKey());
    return 'Icon V3 fixed!';
});


Route::get('/debug-notifications', function() {
    return \Illuminate\Support\Facades\DB::table('notifications')->orderBy('created_at', 'desc')->limit(5)->get();
});


Route::get('/fix-icon-v4', function() {
    \Illuminate\Support\Facades\DB::table('categories')
        ->where('id', 1)
        ->update(['icon' => 'new-spare-parts-icon-v4.png']);
    \App\Models\Category::forgetCategoriesCached();
    return 'Done v4';
});


Route::get('/fix-icon-v5', function() {
    \Illuminate\Support\Facades\DB::table('categories')
        ->where('id', 1)
        ->update(['cat_icon_path' => 'new-spare-parts-icon-v4.png']);
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    return 'Done v5';
});


Route::get('/fix-icon-v4', function() {
    \Illuminate\Support\Facades\DB::table('categories')
        ->where('id', 3)
        ->update(['cat_icon_path' => 'new-spare-parts-icon-v4.png']);
    \App\Models\CacheStaticDataVersion::updateTimestamp(\App\Enums\EntityNameCacheStaticDataEnum::Categories->value);
    \App\Utils\CacheUtils::forget(\App\Utils\CacheUtils::categoriesCacheStaticDataAppKey());
    return 'Icon V4 fixed!';
});


Route::get('/fix-icons-v6', function() {
    \Illuminate\Support\Facades\DB::table('categories')
        ->where('id', 1)
        ->update(['cat_icon_path' => 'new-cars-icon.png']);
    \Illuminate\Support\Facades\DB::table('categories')
        ->where('id', 3)
        ->update(['cat_icon_path' => 'spare-parts-icon.png']);
        
    \App\Models\CacheStaticDataVersion::updateTimestamp(\App\Enums\EntityNameCacheStaticDataEnum::Categories->value);
    \App\Utils\CacheUtils::forget(\App\Utils\CacheUtils::categoriesCacheStaticDataAppKey());
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    return 'Icons restored!';
});


Route::get('/fix-icon-v4', function() {
    \Illuminate\Support\Facades\DB::table('categories')
        ->where('id', 3)
        ->update(['cat_icon_path' => 'new-spare-parts-icon-v4.png']);
    \App\Models\CacheStaticDataVersion::updateTimestamp(\App\Enums\EntityNameCacheStaticDataEnum::Categories->value);
    \App\Utils\CacheUtils::forget(\App\Utils\CacheUtils::categoriesCacheStaticDataAppKey());
    return 'Icon V4 fixed!';
});


Route::get('/logs', function() {
    return file_exists(storage_path('logs/laravel.log')) ? file_get_contents(storage_path('logs/laravel.log')) : 'no logs';
});

