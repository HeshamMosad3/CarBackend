<?php

namespace App\Http\Controllers\API\V1\Shared\Conversations;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\MessageConversation;
use App\Models\RequestCustomer;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function index()
    {
    }

    public function status($conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);

        return buildApiResponseHelper(true, 'تم التحميل بنجاح', [
            'is_request_canceled' => $conversation->isRequestCanceled(),
        ]);
    }

    private function fixVendorConversationIds()
    {
        try {
            Vendor::whereNotNull('user_id')->where('user_id', '>', 0)->get()->each(function ($v) {
                Conversation::where('vendor_id', $v->id)->update(['vendor_id' => $v->user_id]);
            });
        } catch (\Throwable $e) {
        }
    }

    public function getUserConversations(Request $request)
    {
        $this->fixVendorConversationIds();
        $userId = getCurrUserIdHelper();

        $conversations = Conversation::leftJoin('vendors', function ($join) {
            $join->on('vendors.user_id', '=', 'conversations.vendor_id')
                ->orOn('vendors.id', '=', 'conversations.vendor_id');
        })
            ->leftJoin('users as vendor_user', function ($join) {
                $join->on('vendor_user.id', '=', 'vendors.user_id')
                    ->orOn('vendor_user.id', '=', 'conversations.vendor_id');
            })
            ->leftJoin('message_conversations', 'message_conversations.conversation_id', '=', 'conversations.id')
            ->where('conversations.user_id', $userId)
            ->when($request->has('request_id'), function ($query) use ($request) {
                $query->where('conversations.request_id', $request->request_id);
            })
            ->select([
                DB::raw('MAX(conversations.id) as id'),
                'conversations.request_id',
                'conversations.response_id',
                'conversations.vendor_id',
                DB::raw('COALESCE(NULLIF(NULLIF(vendors.company_name_ar, "غير محدد"), ""), vendor_user.name, "غير محدد") as receiver_name'),
                DB::raw('MAX(vendor_user.logo) as receiver_logo'),
                DB::raw('MAX(message_conversations.created_at) as last_activity'),
            ])
            ->groupBy('conversations.request_id', 'conversations.response_id', 'conversations.vendor_id', 'vendors.company_name_ar', 'vendor_user.name')
            ->orderByRaw('COALESCE(MAX(message_conversations.created_at), MAX(conversations.created_at)) DESC')
            ->paginate(10);

        return buildApiResponseHelper(true, 'تم التحميل بنجاح', resultApiPaginationHelper($conversations));
    }

    public function getVendorConversations(Request $request)
    {
        $this->fixVendorConversationIds();
        $userId = getCurrUserIdHelper();
        $vendorTableId = Vendor::where('user_id', $userId)->value('id');

        $conversations = Conversation::leftJoin('users as customer_user', 'conversations.user_id', '=', 'customer_user.id')
            ->where(function ($query) use ($userId, $vendorTableId) {
                $query->where('conversations.vendor_id', $userId);
                if ($vendorTableId) {
                    $query->orWhere('conversations.vendor_id', $vendorTableId);
                }
            })
            ->select([
                DB::raw('MAX(conversations.id) as id'),
                'conversations.request_id',
                'conversations.response_id',
                'conversations.vendor_id',
                DB::raw('COALESCE(NULLIF(NULLIF(customer_user.name, "التاجر"), ""), "العميل") as receiver_name'),
                DB::raw('MAX(customer_user.logo) as receiver_logo'),
            ])
            ->groupBy('conversations.request_id', 'conversations.response_id', 'conversations.vendor_id', 'customer_user.name')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return buildApiResponseHelper(true, 'تم التحميل بنجاح', resultApiPaginationHelper($conversations));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'requestId' => 'required|integer',
            'responseId' => 'nullable|integer',
            'vendorId' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $this->fixVendorConversationIds();

        $requestCustomer = RequestCustomer::find($request->requestId);
        $rawVendorId = $request->vendorId;
        $vendorUserId = Vendor::where('id', $rawVendorId)->value('user_id') ?: $rawVendorId;
        $vendorTableId = Vendor::where('user_id', $vendorUserId)->value('id') ?: $rawVendorId;

        $currentUserId = getCurrUserIdHelper();

        if ($requestCustomer && $requestCustomer->user_id) {
            $customerUserId = $requestCustomer->user_id;
        } else {
            $customerUserId = ($currentUserId != $vendorUserId && $currentUserId != $vendorTableId) ? $currentUserId : 0;
        }

        // البحث عن أي محادثة سابقة مرتبطة بنفس الطلب وتحديثها
        $conversation = Conversation::where('request_id', $request->requestId)
            ->where(function ($q) use ($vendorUserId, $vendorTableId, $rawVendorId) {
                $q->where('vendor_id', $vendorUserId)
                    ->orWhere('vendor_id', $vendorTableId)
                    ->orWhere('vendor_id', $rawVendorId);
            })
            ->latest('id')
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'vendor_id' => $vendorUserId,
                'user_id' => $customerUserId,
                'request_id' => $request->requestId,
                'response_id' => $request->responseId ?? 0,
            ]);
        } else {
            $updateData = ['vendor_id' => $vendorUserId];
            if ($customerUserId > 0) {
                $updateData['user_id'] = $customerUserId;
            }
            $conversation->update($updateData);
        }

        // تنظيف المحادثات المكررة للطلب
        Conversation::where('request_id', $request->requestId)
            ->where('id', '!=', $conversation->id)
            ->delete();

        return $conversation ? buildApiResponseHelper(true, 'تمت العملية بنجاح', ['conversationId' => $conversation->id]) : buildApiResponseHelper(false, 'حدث خطأ');
    }
}



