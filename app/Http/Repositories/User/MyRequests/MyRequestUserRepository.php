<?php

namespace App\Http\Repositories\User\MyRequests;

use App\Models\BrandCar;
use App\Models\City;
use App\Models\CustomField;
use App\Models\RequestBrandScope;
use App\Models\RequestCustomer;
use App\Models\RequestCustomFieldValue;
use App\Models\RequestImage;
use App\Models\RequestResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MyRequestUserRepository
{
    public function getMyRequest()
    {
        // JSON_UNQUOTE is MySQL-only; SQLite (local dev) uses json_extract directly.
        $notificationTargetId = DB::connection()->getDriverName() === 'sqlite'
            ? 'json_extract(data, "$.target_id")'
            : 'JSON_UNQUOTE(JSON_EXTRACT(data, "$.target_id"))';

        return RequestCustomer::leftJoinCity()
            ->leftJoinCategory()
            ->where('request_customers.user_id', getCurrUserIdHelper())
            ->selectRaw(
                'request_customers.id as request_id,
                request_customers.status as request_status,
                categories.cat_name_ar,
                request_customers.created_at as request_date,
                cities.city_name_ar as city_customer_name_ar,
                (SELECT COUNT(id) FROM request_responses WHERE request_responses.request_id = request_customers.id) as count_response,
                (
                    (SELECT COUNT(*) FROM conversations 
                     JOIN message_conversations ON message_conversations.conversation_id = conversations.id
                     WHERE conversations.request_id = request_customers.id 
                     AND message_conversations.sender_id != request_customers.user_id
                     AND (message_conversations.read = 0 OR message_conversations.read IS NULL)
                    ) 
                    + 
                    (SELECT COUNT(*) FROM notifications
                     WHERE notifiable_id = request_customers.user_id
                     AND read_at IS NULL
                     AND notifiable_type LIKE "%User%"
                     AND (' . $notificationTargetId . ' = request_customers.id)
                    )
                ) as unread_activity_count
                '
            )
            ->orderBy('unread_activity_count', 'desc')
            ->orderBy('request_customers.id', 'desc')
            ->paginate(10);
    }

    public function getMyRequestById(int $requestId)
    {
        return RequestCustomer::leftJoinCategory()
            ->leftJoinCity()
            ->where('request_customers.id', $requestId)
            ->where('request_customers.user_id', getCurrUserIdHelper())
            ->select(
                'request_customers.id as request_id',
                'categories.cat_name_ar',
                'request_customers.created_at as request_date',
                'cities.city_name_ar as city_customer_name_ar',
                'request_customers.description',
                'request_customers.cities_ids_scope as cities',
                'request_customers.status as request_status',
            )
            ->first();
    }

    public function getRequestCitiesNamesScope($cityIdsScope)
    {
        if (empty($cityIdsScope)) {
            return [];
        }

        $citiesCached = City::getCitiesCached();
        $cityList = is_array($cityIdsScope) ? $cityIdsScope : json_decode($cityIdsScope, true);

        if (!is_array($cityList)) {
            return [];
        }

        $citiesNames = [];
        foreach ($cityList as $city) {
            $name = $citiesCached->where('id', (int) $city)->value('city_name_ar');
            if ($name) {
                $citiesNames[] = $name;
            }
        }

        return $citiesNames;
    }

    public function getRequestImages($requestId)
    {
        return RequestImage::where('request_id', $requestId)->get(['image_name']);
    }

    public function getRequestBrandNamesScope($requestId)
    {
        $brandIdsScope = RequestBrandScope::where('request_id', $requestId)->first(['brand_ids_scope']);
        if (!$brandIdsScope || empty($brandIdsScope->brand_ids_scope)) {
            return [];
        }

        $BrandCarsCached = BrandCar::getBrandCarsCached();
        $brandScopeList = is_array($brandIdsScope->brand_ids_scope) ? $brandIdsScope->brand_ids_scope : json_decode($brandIdsScope->brand_ids_scope, true);

        if (!is_array($brandScopeList)) {
            return [];
        }

        $brandsNames = [];
        foreach ($brandScopeList as $brand) {
            $name = $BrandCarsCached->where('id', (int) $brand)->value('brand_name_ar');
            if ($name) {
                $brandsNames[] = $name;
            }
        }

        return $brandsNames;
    }

    public function getRequestCustomFields($requestId)
    {
        $requestCustomFields = RequestCustomFieldValue::where('request_id', $requestId)->get();
        $customFieldsCached = CustomField::getCustomFieldsCached();

        $result = [];
        foreach ($requestCustomFields as $item) {
            $temp = [];
            $temp['key'] = $customFieldsCached->where('id', $item->custom_field_id)->value('label_ar') ?? '';
            $temp['value'] = is_string($item->value) ? (json_decode($item->value, true) ?? $item->value) : $item->value;
            array_push($result, $temp);
        }

        return $result;
    }

    public function getResponsesMyRequest(int $requestId)
    {
        Log::info('------------getResponsesMyRequest----------');
        return RequestResponse::joinRequestCustomer()
            ->leftJoinVendor()
            ->leftJoinVendorToUser()
            ->leftJoinShippingRequest()
            ->where('request_responses.request_id', $requestId)
            ->where('request_customers.user_id', getCurrUserIdHelper())
            ->select(
                'request_responses.id as response_id',
                'request_responses.status as response_status',
                'request_responses.created_at as response_date',
                'request_responses.price as price_response',
                'request_responses.warranty as warranty_response',
                'vendors.company_name_ar',
                'users.logo as vendor_logo',
                'shipping_requests.id as shipping_request_id',
                'shipping_requests.status as shipping_request_status',
                \Illuminate\Support\Facades\DB::raw("(SELECT COUNT(message_conversations.id) FROM message_conversations INNER JOIN conversations ON conversations.id = message_conversations.conversation_id WHERE conversations.response_id = request_responses.id AND message_conversations.read = 0 AND message_conversations.sender_id != " . getCurrUserIdHelper() . ") as unread_messages_count"),
                \Illuminate\Support\Facades\DB::raw("COALESCE((SELECT MAX(message_conversations.created_at) FROM message_conversations INNER JOIN conversations ON conversations.id = message_conversations.conversation_id WHERE conversations.response_id = request_responses.id), request_responses.created_at) as last_activity")
            )
            ->orderBy('unread_messages_count', 'desc')
            ->orderBy('last_activity', 'desc')
            ->paginate(20);
    }

    public function getResponseRequestById($responseId)
    {
        return RequestResponse::joinRequestCustomer()
            ->leftJoinVendor()
            ->leftJoinVendorToUser()
            ->where('request_responses.id', $responseId)
            ->where('request_customers.user_id', getCurrUserIdHelper())
            ->select(
                'request_responses.id as response_id',
                'request_responses.request_id',
                'request_responses.vendor_id',
                'request_responses.status as response_status',
                'request_responses.created_at as response_date',
                'request_responses.price as price_response',
                'request_responses.note as note_response',
                'request_responses.warranty as warranty_response',
                'vendors.company_name_ar',
                'vendors.phone_contact',
                'vendors.is_hide_phone_contact',
                'users.logo as vendor_logo',
                'users.created_at as vendor_member_since',
            )
            ->first();
    }
}
