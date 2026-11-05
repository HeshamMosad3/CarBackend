<?php

namespace App\Http\Services\Shared\Notifications;

use App\Models\Conversation;
use App\Models\RequestCustomer;
use App\Models\ShippingRequest;
use App\Models\User;
use App\Models\Vendor;

/**
 * Where tapping a notification should take the user. Used for the rows of the notifications list
 * and for the data of a push, so both open exactly the same screen.
 *
 * Routes (understood by the app's NotificationRouter):
 *   chat              the chat of an order (target_id = conversation id) — also where a shipment's
 *                     "pay / approve / complete data" buttons are
 *   request_responses the offers on one of the customer's requests (target_id = request id)
 *   request_details   one request a vendor can answer (target_id = request id)
 *   home              nothing more specific: back to the app's main screen
 */
class NotificationDestinationResolver
{
    /**
     * @return array{route:string, target_id:?string, params:array<string,string>}
     */
    public function resolve(int $userId, ?string $category, ?string $targetId, ?string $body = null): array
    {
        return match ($category ?: 'generic') {
            'chat_message' => $this->forConversation($userId, (int) $targetId) ?? $this->destination('chat', $targetId),
            'vendor_response', 'request_update' => $this->destination('request_responses', $targetId),
            'new_request' => $this->destination('request_details', $targetId),
            'shipment_update' => $this->forShipment($userId, (int) $targetId) ?? $this->home(),
            'vendor_status' => $this->home(),
            default => $this->forLegacyGeneric($userId, (string) $body),
        };
    }

    /**
     * Notifications saved before the specific categories existed are all "generic" and only say which
     * order they are about in their text ("... الطلب رقم ( 4 ) ..."), so recover the order from there.
     */
    private function forLegacyGeneric(int $userId, string $body): array
    {
        if (!preg_match('/\(\s*(\d+)\s*\)/u', $body, $m)) {
            return $this->home();
        }
        $requestId = (int) $m[1];

        $shipment = ShippingRequest::where('request_id', $requestId)->orderByDesc('id')->first();
        if ($shipment && ($destination = $this->forShipment($userId, $shipment->id))) {
            return $destination;
        }

        if (RequestCustomer::where('id', $requestId)->where('user_id', $userId)->exists()) {
            return $this->destination('request_responses', (string) $requestId);
        }

        return $this->home();
    }

    private function forShipment(int $userId, int $shippingId): ?array
    {
        $shipment = ShippingRequest::find($shippingId);
        if (!$shipment) {
            return null;
        }

        $vendorTableId = Vendor::where('user_id', $userId)->value('id');

        $conversation = Conversation::where('request_id', $shipment->request_id)
            ->where('response_id', $shipment->response_id)
            ->where(function ($q) use ($userId, $vendorTableId) {
                $q->where('user_id', $userId)->orWhere('vendor_id', $userId);
                if ($vendorTableId) {
                    $q->orWhere('vendor_id', $vendorTableId);
                }
            })
            ->orderByDesc('id')
            ->first();

        return $conversation ? $this->conversationDestination($userId, $conversation) : null;
    }

    private function forConversation(int $userId, int $conversationId): ?array
    {
        $conversation = $conversationId > 0 ? Conversation::find($conversationId) : null;

        return $conversation ? $this->conversationDestination($userId, $conversation) : null;
    }

    /** Same fields (and the same naming of the other party) as the conversations lists. */
    private function conversationDestination(int $userId, Conversation $conversation): array
    {
        if ((int) $conversation->user_id === $userId) {
            $vendor = Vendor::where(function ($q) use ($conversation) {
                $q->where('user_id', $conversation->vendor_id)->orWhere('id', $conversation->vendor_id);
            })->first();

            $company = $vendor?->company_name_ar;
            $name = ($company && $company !== 'غير محدد')
                ? $company
                : (User::where('id', $vendor?->user_id ?? $conversation->vendor_id)->value('name') ?: 'غير محدد');
        } else {
            $customer = User::where('id', $conversation->user_id)->value('name');
            $name = ($customer && $customer !== 'التاجر') ? $customer : 'العميل';
        }

        return [
            'route' => 'chat',
            'target_id' => (string) $conversation->id,
            'params' => [
                'request_id' => (string) $conversation->request_id,
                'response_id' => (string) ($conversation->response_id ?? 0),
                'vendor_id' => (string) $conversation->vendor_id,
                'receiver_name' => $name,
            ],
        ];
    }

    private function destination(string $route, ?string $targetId): array
    {
        return ['route' => $route, 'target_id' => $targetId !== null ? (string) $targetId : null, 'params' => []];
    }

    private function home(): array
    {
        return $this->destination('home', null);
    }
}
