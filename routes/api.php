<?php

use App\Http\Controllers\API\V1\Shared\CacheStaticDataVersionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['auth:sanctum']]);
require base_path('routes/channels.php');

Route::prefix('v1/user')->group(base_path('routes/api_user_v1.php'));
Route::prefix('v1/vendor')->group(base_path('routes/api_vendor_v1.php'));

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::prefix('/chat/messages')->controller(App\Http\Controllers\API\V1\Shared\Conversations\MessageConversationController::class)->group(function () {
        Route::get('/{conversationId}', 'index');
        Route::post('/send', 'store');
    });
    Route::prefix('/chat/conversations')->controller(App\Http\Controllers\API\V1\Shared\Conversations\ConversationController::class)->group(function () {
        Route::post('/create-conversation', 'store');
        Route::get('/user-conversations', 'getUserConversations');
        Route::get('/vendor-conversations', 'getVendorConversations');
        Route::get('/{conversationId}/status', 'status');
    });

    Route::prefix('/notifications')->group(function () {
        Route::get('/unread-counts', [App\Http\Controllers\API\NotificationBadgeController::class, 'unreadCounts']);
        Route::post('/mark-category-read', [App\Http\Controllers\API\NotificationBadgeController::class, 'markCategoryRead']);
        Route::post('/mark-entity-read', [App\Http\Controllers\API\NotificationBadgeController::class, 'markEntityRead']);
        Route::get('/', [App\Http\Controllers\API\V1\Shared\NotificationController::class, 'index']);

        Route::prefix('/devices')->controller(App\Http\Controllers\API\V1\Shared\Devices\UserDeviceController::class)->group(function () {
            Route::post('/register', 'register');
            Route::post('/unregister', 'unregister');
        });
    });
});

Route::get('/uploads/{filename}', [App\Http\Controllers\FileController::class, 'getImage']);

Route::middleware('auth:sanctum')->controller(App\Http\Controllers\FileController::class)->group(function () {
    Route::get('/uploads-private/{filename}', 'getSensitiveImage');
});

Route::prefix('v1/auth')->controller(App\Http\Controllers\API\V1\Shared\Auth\AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login-with-otp', 'loginWithOtp');
    Route::post('/logout', 'logout')->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->controller(App\Http\Controllers\FileController::class)->group(function () {
    Route::get('/{filename}', 'getSensitiveImage');
});

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/cities', [App\Http\Controllers\API\V1\Shared\CityController::class, 'getCities']); 
});

Route::prefix('v1')->group(function () {
    Route::post('/cache/check-updates', [CacheStaticDataVersionController::class, 'checkUpdates']);
    
    Route::get('/test-db', function () {
        $vendor = \App\Models\RequestResponse::joinRequestCustomer()
            ->leftJoinVendor()
            ->leftJoinVendorToUser()
            ->select('request_responses.id as response_id', 'users.logo as vendor_logo', 'vendors.id as v_id', 'vendors.user_id as vu_id')
            ->first();
        return response()->json($vendor);
    });
    
    Route::get('/update-cat', function () {
        \App\Models\Category::where('id', 2)->update([
            'cat_name_ar' => 'قطع غيار تشليح',
            'cat_name_en' => 'Scrap Spare Parts'
        ]);
        
        \App\Models\CacheStaticDataVersion::where('entity_name', 'categories')->update(['last_updated_at' => now()]);
        \App\Models\CacheStaticDataVersion::where('entity_name', 'category_has_brand_field')->update(['last_updated_at' => now()]);
        
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        
        return response()->json(['success' => true]);
    });
});
Route::get('/test-railway', function () { return 'Railway is deploying!'; });

Route::get('/create-demo-accounts', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

    $customer = \App\Models\User::updateOrCreate(
        ['phone' => '0512345678'],
        [
            'name' => 'عميل تجريبي',
            'status' => \App\Enums\StatusUserEnum::Active->value,
        ]
    );
    $customer->syncRoles(['user']);

    $vendorUser = \App\Models\User::updateOrCreate(
        ['phone' => '0555555555'],
        [
            'name' => 'شركة الأمل لقطع الغيار',
            'status' => \App\Enums\StatusUserEnum::Active->value,
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
        'message' => 'Demo accounts created successfully',
        'customer' => [
            'type' => 'زبون (Customer)',
            'phone' => '0512345678',
            'otp' => '11111',
        ],
        'vendor' => [
            'type' => 'شركة / ورشة (Vendor)',
            'phone' => '0555555555',
            'otp' => '11111',
        ],
    ]);
});
