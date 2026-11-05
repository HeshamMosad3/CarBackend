<?php

namespace App\Traits;

use App\Enums\Notifications\NotificationCategoryEnum;
use App\Http\Services\Shared\Notifications\NotificationDestinationResolver;
use App\Http\Services\Shared\Notifications\NotificationDispatcherService;
use Illuminate\Http\Request;

trait NotificationsTrait
{
    protected function dispatcher(): NotificationDispatcherService
    {
        return app(NotificationDispatcherService::class);
    }

    public function notifyToAdmin(
        string $title,
        string $body,
        NotificationCategoryEnum $category = NotificationCategoryEnum::Generic,
        $targetId = null,
    ) {
        $this->dispatcher()->toAdmins($category, $title, $body, $targetId !== null ? (string) $targetId : null);
    }

    /**
     * @param iterable $vendors Vendor rows/models carrying at least `user_id`.
     */
    public function notifyRequestToEligibleVendors($vendors, ?int $requestId = null)
    {
        $userIds = collect($vendors)->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->values()->all();

        $this->dispatcher()->toUsers(
            userIds: $userIds,
            category: NotificationCategoryEnum::NewRequest,
            title: 'طلب جديد',
            body: 'تم اضافة طلب جديد',
            targetId: $requestId !== null ? (string) $requestId : null,
        );
    }

    /**
     * @param NotificationCategoryEnum|string $category Accepts the old raw
     *        category strings too, so callers that haven't been updated yet
     *        still work during the migration.
     */
    public function notifyByID(
        $userId,
        string $title,
        string $body,
        bool $notifyDB = true,
        NotificationCategoryEnum|string $category = NotificationCategoryEnum::ChatMessage,
        $targetId = null,
        array $extraData = [],
    ) {
        if (is_string($category)) {
            $category = $this->legacyStringToCategory($category);
        }

        $this->dispatcher()->toUser(
            userId: (int) $userId,
            category: $category,
            title: $title,
            body: $body,
            targetId: $targetId !== null ? (string) $targetId : null,
            params: $extraData,
            persist: $notifyDB,
        );
    }

    private function legacyStringToCategory(string $legacy): NotificationCategoryEnum
    {
        return match ($legacy) {
            'conversations' => NotificationCategoryEnum::ChatMessage,
            'company_responses' => NotificationCategoryEnum::VendorResponse,
            'customer_requests' => NotificationCategoryEnum::NewRequest,
            'shipment_update' => NotificationCategoryEnum::ShipmentUpdate,
            'request_update' => NotificationCategoryEnum::RequestUpdate,
            default => NotificationCategoryEnum::Generic,
        };
    }

    public function getNotifications(Request $request)
    {
        $user = currUserHelper();
        $notifications = $user->notifications()
            ->select('id', 'data', 'category', 'target_id', 'read_at', 'created_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $resolver = app(NotificationDestinationResolver::class);

        $notifications->getCollection()->transform(function ($item) use ($user, $resolver) {
            $data = $item->data;

            return [
                'id' => $item->id,
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'category' => $item->category,
                'target_id' => $item->target_id,
                // Where tapping this notification should go (see NotificationDestinationResolver).
                'destination' => $resolver->resolve((int) $user->id, $item->category, $item->target_id, $data['body'] ?? ''),
                'read_at' => $item->read_at?->format('Y-m-d H:i'),
                'created_at' => $item->created_at->format('Y-m-d H:i'),
            ];
        });

        return buildApiResponseHelper(true, 'تم التحميل بنجاح', [
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
            'total' => $notifications->total(),
            'per_page' => $notifications->perPage(),
            'data' => $notifications->items(),
        ]);
    }
}
