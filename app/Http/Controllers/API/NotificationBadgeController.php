<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Services\Shared\Notifications\NotificationCountsService;
use App\Models\Conversation;
use App\Models\MessageConversation;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationBadgeController extends Controller
{
    public function __construct(protected NotificationCountsService $counts) {}

    /**
     * Get unread notification counts grouped by section and per-entity.
     */
    public function unreadCounts(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            return response()->json([
                'success' => true,
                'data' => $this->counts->unreadCountsForUser($user->id),
            ]);
        } catch (\Throwable $e) {
            Log::error("[NotificationBadgeController] unreadCounts ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ غير متوقع. الرجاء المحاولة لاحقاً.'
            ], 500);
        }
    }

    /**
     * Mark a specific entity (e.g. conversation_id or request_id) as read.
     */
    public function markEntityRead(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $section = $request->input('section');
            $entityId = $request->input('entity_id');
            $userId = $user->id;

            if ($section === 'conversations' && $entityId) {
                MessageConversation::where('conversation_id', $entityId)
                    ->where('sender_id', '!=', $userId)
                    ->update(['read' => 1]);
            } elseif (in_array($section, ['customer_requests', 'company_responses'], true) && $entityId) {
                // Exact match only — the old code fell back to marking the
                // first unread notification when nothing matched target_id,
                // which read the wrong notification as read.
                DB::table('notifications')
                    ->where('notifiable_type', get_class($user))
                    ->where('notifiable_id', $userId)
                    ->where('badge_category', $section)
                    ->where('target_id', (string) $entityId)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }

            return $this->unreadCounts($request);
        } catch (\Throwable $e) {
            Log::error("[NotificationBadgeController] markEntityRead ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ غير متوقع. الرجاء المحاولة لاحقاً.'
            ], 500);
        }
    }

    /**
     * Mark every status-update notification as read (what the bell counts) — called when the user
     * opens the notifications screen. Chat and "new request" notifications are left alone: they are
     * cleared by opening the chat / the request, so their own badges stay accurate.
     */
    public function markAllRead(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            DB::table('notifications')
                ->where('notifiable_type', get_class($user))
                ->where('notifiable_id', $user->id)
                ->whereNull('read_at')
                ->where(function ($q) {
                    $q->whereNull('category')->orWhereNotIn('category', NotificationCountsService::BELL_EXCLUDED_CATEGORIES);
                })
                ->update(['read_at' => now()]);

            return $this->unreadCounts($request);
        } catch (\Throwable $e) {
            Log::error("[NotificationBadgeController] markAllRead ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ غير متوقع. الرجاء المحاولة لاحقاً.'
            ], 500);
        }
    }

    /**
     * Mark notifications for a specific category/section as read.
     */
    public function markCategoryRead(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $category = $request->input('category');
            $userId = $user->id;

            if (in_array($category, ['customer_requests', 'company_responses'], true)) {
                // Scoped to the requested category — the old code marked
                // EVERY unread notification read regardless of $category.
                DB::table('notifications')
                    ->where('notifiable_type', get_class($user))
                    ->where('notifiable_id', $userId)
                    ->where('badge_category', $category)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            } elseif ($category === 'conversations') {
                $vendorId = Vendor::where('user_id', $userId)->value('id');

                $userConversationIds = Conversation::where('user_id', $userId)
                    ->when($vendorId, fn ($q) => $q->orWhere('vendor_id', $vendorId))
                    ->pluck('id');

                if ($userConversationIds->isNotEmpty()) {
                    MessageConversation::whereIn('conversation_id', $userConversationIds)
                        ->where('sender_id', '!=', $userId)
                        ->update(['read' => 1]);
                }
            }

            return $this->unreadCounts($request);
        } catch (\Throwable $e) {
            Log::error("[NotificationBadgeController] markCategoryRead ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ غير متوقع. الرجاء المحاولة لاحقاً.'
            ], 500);
        }
    }
}
