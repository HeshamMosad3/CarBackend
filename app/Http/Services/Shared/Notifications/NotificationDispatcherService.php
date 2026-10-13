<?php

namespace App\Http\Services\Shared\Notifications;

use App\Enums\Notifications\NotificationCategoryEnum;
use App\Enums\user\UserRoleEnum;
use App\Http\Services\BaseService;
use App\Jobs\FanOutPushNotificationJob;
use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use App\Notifications\Payloads\PushPayload;
use App\Notifications\SendNotification;
use Illuminate\Support\Str;

/**
 * The single seam every notification call-site goes through: persist the DB
 * row (optional), dispatch the queued push, broadcast the realtime badge
 * update. NotificationsTrait is now a thin adapter over this.
 */
class NotificationDispatcherService extends BaseService
{
    public function __construct(protected NotificationBadgeBroadcaster $badgeBroadcaster) {}

    public function toUser(
        int $userId,
        NotificationCategoryEnum $category,
        string $title,
        string $body,
        ?string $targetId = null,
        array $params = [],
        bool $persist = true,
    ): void {
        if ($userId <= 0) {
            return;
        }

        $notificationId = (string) Str::uuid();

        if ($persist) {
            $this->persist($userId, $title, $body, $category, $targetId, $notificationId);
        }

        $payload = new PushPayload(
            recipientId: $userId,
            category: $category,
            title: $title,
            body: $body,
            targetId: $targetId,
            params: $params,
            notificationId: $notificationId,
        );

        try {
            SendPushNotificationJob::dispatch($payload->toArray());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Notification] Push dispatch failed: ' . $e->getMessage());
        }

        try {
            $this->badgeBroadcaster->broadcast($userId, $category);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Notification] Badge broadcast failed: ' . $e->getMessage());
        }
    }

    /**
     * @param int[] $userIds
     */
    public function toUsers(
        array $userIds,
        NotificationCategoryEnum $category,
        string $title,
        string $body,
        ?string $targetId = null,
        array $params = [],
        bool $persist = true,
    ): void {
        $userIds = array_values(array_unique(array_filter($userIds, fn ($id) => (int) $id > 0)));
        if (empty($userIds)) {
            return;
        }

        if ($persist) {
            foreach ($userIds as $userId) {
                $this->persist($userId, $title, $body, $category, $targetId, (string) Str::uuid());
            }
        }

        // recipient_id is a placeholder here — FanOutPushNotificationJob
        // overwrites it per user before dispatching each SendPushNotificationJob.
        $template = new PushPayload(
            recipientId: 0,
            category: $category,
            title: $title,
            body: $body,
            targetId: $targetId,
            params: $params,
        );

        FanOutPushNotificationJob::dispatch($userIds, $template->toArray());
    }

    public function toAdmins(
        NotificationCategoryEnum $category,
        string $title,
        string $body,
        ?string $targetId = null,
        array $params = [],
    ): void {
        $adminIds = User::role([
            UserRoleEnum::Super_Admin->value,
            UserRoleEnum::Admin->value,
        ], 'admin')->pluck('id')->all();

        $this->toUsers($adminIds, $category, $title, $body, $targetId, $params);
    }

    private function persist(
        int $userId,
        string $title,
        string $body,
        NotificationCategoryEnum $category,
        ?string $targetId,
        string $notificationId,
    ): void {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $notification = new SendNotification(title: $title, body: $body, category: $category, targetId: $targetId);
        $notification->id = $notificationId;
        $user->notify($notification);
    }
}
