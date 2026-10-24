<?php

namespace App\Http\Controllers\API\V1\Shared\Conversations;

use App\Enums\Notifications\NotificationCategoryEnum;
use App\Http\Controllers\Controller;
use App\Http\Services\Shared\ShippingService;
use App\Models\Conversation;
use App\Models\MessageConversation;
use App\Traits\NotificationsTrait;
use App\Utils\UploadUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MessageConversationController extends Controller
{
    use NotificationsTrait;

    public function __construct(protected ShippingService $shippingService) {}

    public function index(Request $request, $conversationId)
    {
        $lastId = $request->query('last_message_id', 0);

        $messages = MessageConversation::where('conversation_id', $conversationId)
            ->where('id', '>', $lastId)
            ->select(
                'id',
                'sender_id',
                'body',
                'image',
                'is_shipping_request',
                'created_at as date_sent',
            )
            ->orderBy('id', 'asc')
            ->get();

        return buildApiResponseHelper(true, 'تم ارسال الرسالة بنجاح', $messages);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'conversationId' => 'required|exists:conversations,id',
            'requestId' => 'required|integer',
            'responseId' => 'required|integer',
            'body' => 'nullable|string',
            'isSendShippingRequest' => 'nullable|boolean',
            'shippingInfo' => 'nullable',
            'image' => 'nullable|image|mimes:png,jpg,jpeg,gif,webp|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $userId = getCurrUserIdHelper();
        $receiverId = Conversation::getReceiverId($request->conversationId, $userId);

        // Once the customer cancels the request the chat stays readable, but the vendor can't write to it.
        $conversation = Conversation::find($request->conversationId);
        if ($conversation && (int) $userId !== (int) $conversation->user_id && $conversation->isRequestCanceled()) {
            return buildApiResponseHelper(false, 'لا يمكن إرسال رسالة لأن الطلب ملغي');
        }

        $fileName = UploadUtils::uploadImageToStorage($request->image);

        $created = MessageConversation::create([
            'conversation_id' => $request->conversationId,
            'sender_id' => $userId,
            'body' => $request->body,
            'is_shipping_request' => $request->isSendShippingRequest,
            'image' => $fileName
        ]);


        if ($created) {
            $messagesNotify =  'رسالة جديدة من الطلب رقم' . ' ( ' . $request->requestId . ' )';
            if ($request->shippingInfo != null && $request->isSendShippingRequest == true && $request->shippingInfo != '') {
                $shippingInfo = json_decode($request->shippingInfo, true);
                $this->shippingService->storeShippingRequest(
                    requestId: $request->requestId,
                    responseId: $request->responseId,
                    orderNumber: 'REQ-' . $request->requestId . '-RES-' . $request->responseId . '-MSG-' . $created->id,
                    nameOriginVendor: $shippingInfo['name'] ?? null,
                    cityOriginVendor: !empty($shippingInfo['city']) ? $shippingInfo['city'] : 'الرياض',
                    addressOriginVendor: $shippingInfo['address'],
                    latOriginVendor: isset($shippingInfo['lat']) ? (float) $shippingInfo['lat'] : null,
                    lngOriginVendor: isset($shippingInfo['lng']) ? (float) $shippingInfo['lng'] : null,
                    phoneOriginVendor: $shippingInfo['phone'],
                    length: $shippingInfo['length'],
                    width: $shippingInfo['width'],
                    height: $shippingInfo['height'] ?? null,
                    weight: $shippingInfo['weight'] ?? null,
                    packages: isset($shippingInfo['packages']) ? json_encode($shippingInfo['packages']) : null
                );
                $messagesNotify =  'طلب شحن جديد من الطلب رقم' . ' ( ' . $request->requestId . ' )';
            }

            // Broadcast real-time message via Reverb WebSocket FIRST — the push
            // send below is now a queue insert (~1ms), but it must never be
            // able to delay the in-app realtime message either way.
            try {
                broadcast(new \App\Events\NewMessage($request->conversationId, $created))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Failed to broadcast NewMessage: ' . $e->getMessage());
            }

            if ($receiverId && (int) $receiverId !== (int) $userId) {
                $conversation = Conversation::find($request->conversationId);
                $vendorId = $conversation ? $conversation->vendor_id : 0;

                $senderName = 'المحادثة';
                if ($conversation) {
                    if ((int)$userId === (int)$conversation->user_id) {
                        $userObj = \App\Models\User::find($userId);
                        $senderName = $userObj ? $userObj->name : 'العميل';
                    } else {
                        $vendorObj = \App\Models\Vendor::find($vendorId);
                        $senderName = $vendorObj ? $vendorObj->company_name_ar : 'المورد';
                    }
                }

                $this->notifyByID(
                    userId: $receiverId,
                    title: $messagesNotify,
                    body: $request->body,
                    notifyDB: false,
                    category: NotificationCategoryEnum::ChatMessage,
                    targetId: $request->conversationId,
                    extraData: [
                        'conversation_id' => (string) $request->conversationId,
                        'message_id' => (string) $created->id,
                        'sender_id' => (string) $userId,
                        'request_id' => (string) $request->requestId,
                        'response_id' => (string) $request->responseId,
                        'vendor_id' => (string) $vendorId,
                        'receiver_name' => $senderName,
                        'is_shipping_request' => $request->isSendShippingRequest ? '1' : '0',
                    ]
                );
            }
        }

        return buildApiResponseHelper(true, 'تم ارسال الرسالة بنجاح');
    }
}
