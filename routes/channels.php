<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// The admin dashboard's live channel: any signed-in admin (the "admin" guard), nobody else.
Broadcast::channel(\App\Support\Dashboard\AdminAlerts::CHANNEL, function ($admin) {
    return $admin instanceof \App\Models\Admin;
}, ['guards' => ['admin']]);

Broadcast::channel('user.{id}', function ($user, $id) {
    Log::debug("Broadcast auth attempt on user.{$id} by user {$user->id}");
    return (int) $user->id === (int) $id;
});

// Laravel strips the "private-" prefix before matching, so this single
// callback also authorizes "private-conversation.{id}" — the separate
// duplicate callback that used to exist here was dead weight.
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conv = Conversation::find($conversationId);
    if (!$conv) {
        Log::warning("Broadcast auth failed: Conversation {$conversationId} not found");
        return false;
    }

    $vendorUserId = \App\Models\Vendor::where('id', $conv->vendor_id)->value('user_id') ?: $conv->vendor_id;

    $allowed = (int) $user->id === (int) $vendorUserId || (int) $user->id === (int) $conv->user_id;
    if (!$allowed) {
        Log::warning("Broadcast auth forbidden: User {$user->id} not participant in conversation {$conversationId} (vendor user: {$vendorUserId}, client user: {$conv->user_id})");
    }
    return $allowed;
});
