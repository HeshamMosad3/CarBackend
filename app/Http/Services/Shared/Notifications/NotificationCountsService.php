<?php

namespace App\Http\Services\Shared\Notifications;

use App\Models\Conversation;
use App\Models\MessageConversation;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for unread-badge counts. Used by both
 * NotificationBadgeController (the /unread-counts endpoint) and
 * NotificationBadgeBroadcaster (the realtime event), so the two can never
 * disagree.
 *
 * Replaces the old NotificationBadgeController logic that loaded every
 * unread notification row and classified it by guessing from Arabic
 * substrings in the title/body — this reads the indexed `badge_category`/
 * `target_id` columns instead (see the
 * add_category_columns_to_notifications_table migration).
 */
class NotificationCountsService
{
    public function unreadCountsForUser(int $userId): array
    {
        [$customerRequestsCount, $customerRequestsEntities] = $this->countsByBucket($userId, 'customer_requests');
        [$companyResponsesCount, $companyResponsesEntities] = $this->countsByBucket($userId, 'company_responses');
        [$conversationsCount, $conversationEntities, $requestConversationEntities] = $this->conversationCounts($userId);

        return [
            'customer_requests' => $customerRequestsCount,
            'company_responses' => $companyResponsesCount,
            'conversations' => $conversationsCount,
            'sections' => [
                'customer_requests' => $customerRequestsCount,
                'company_responses' => $companyResponsesCount,
                'conversations' => $conversationsCount,
            ],
            'entities' => [
                'conversations' => $conversationEntities,
                'request_conversations' => $requestConversationEntities,
                'customer_requests' => $customerRequestsEntities,
                'company_responses' => $companyResponsesEntities,
            ],
        ];
    }

    private function countsByBucket(int $userId, string $badgeBucket): array
    {
        $rows = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->where('badge_category', $badgeBucket)
            ->whereNull('read_at')
            ->select('target_id', DB::raw('count(*) as count'))
            ->groupBy('target_id')
            ->get();

        $entities = [];
        $total = 0;

        foreach ($rows as $row) {
            $total += (int) $row->count;
            if ($row->target_id !== null && $row->target_id !== '') {
                $entities[(string) $row->target_id] = (int) $row->count;
            }
        }

        return [$total, $entities];
    }

    /**
     * Counts unread messages across every conversation the user is part of,
     * as the customer or as the vendor (see the vendor_id note below).
     */
    private function conversationCounts(int $userId): array
    {
        $vendorId = Vendor::where('user_id', $userId)->value('id');

        // conversations.vendor_id holds the vendor's users.id (ConversationController
        // normalizes to it), with vendors.id kept as a legacy match.
        $conversationIds = Conversation::where('user_id', $userId)
            ->orWhere('vendor_id', $userId)
            ->when($vendorId, fn ($q) => $q->orWhere('vendor_id', $vendorId))
            ->pluck('id');

        if ($conversationIds->isEmpty()) {
            return [0, [], []];
        }

        $rows = MessageConversation::join('conversations', 'message_conversations.conversation_id', '=', 'conversations.id')
            ->whereIn('message_conversations.conversation_id', $conversationIds)
            ->where('message_conversations.sender_id', '!=', $userId)
            ->where(function ($q) {
                $q->where('message_conversations.read', 0)->orWhereNull('message_conversations.read');
            })
            ->select('message_conversations.conversation_id', 'conversations.request_id', DB::raw('count(*) as count'))
            ->groupBy('message_conversations.conversation_id', 'conversations.request_id')
            ->get();

        $conversationEntities = [];
        $requestEntities = [];
        $total = 0;

        foreach ($rows as $row) {
            $count = (int) $row->count;
            $conversationEntities[(string) $row->conversation_id] = $count;
            if ($row->request_id) {
                $requestEntities[(string) $row->request_id] = ($requestEntities[(string) $row->request_id] ?? 0) + $count;
            }
            $total += $count;
        }

        return [$total, $conversationEntities, $requestEntities];
    }
}
