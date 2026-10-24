<?php

namespace App\Models;

use App\Enums\RequestCustomerStatusEnum;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'user_id',
        'vendor_id',
        'request_id',
        'response_id'
    ];

    public function isRequestCanceled(): bool
    {
        return RequestCustomer::where('id', $this->request_id)
            ->where('status', RequestCustomerStatusEnum::Canceled->value)
            ->exists();
    }

    public static function getReceiverId($conversationId, $currentUserId)
    {
        $conversation = self::where('id', $conversationId)->first(['id', 'vendor_id', 'user_id']);
        if (!$conversation) return 0;

        $vendorUserId = Vendor::where('id', $conversation->vendor_id)->value('user_id') ?: $conversation->vendor_id;

        // If current user is the customer, the receiver is the vendor
        if ((int) $currentUserId === (int) $conversation->user_id) {
            return (int) $vendorUserId;
        }

        // Otherwise (current user is the vendor), the receiver is the customer
        return (int) $conversation->user_id;
    }

    public function scopeGetReceiverId($query, $conversationId, $currentUserId)
    {
        return self::getReceiverId($conversationId, $currentUserId);
    }
}
