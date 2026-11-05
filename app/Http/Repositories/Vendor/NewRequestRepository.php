<?php

namespace App\Http\Repositories\Vendor;

use App\Enums\RequestCustomerStatusEnum;
use App\Models\BrandCar;
use App\Models\City;
use App\Models\CustomField;
use App\Models\RequestBrandScope;
use App\Models\RequestCustomFieldValue;
use App\Models\RequestEligibleVendor;
use App\Models\RequestImage;

class NewRequestRepository
{
    /**
     * The requests a vendor should still act on: matched to them, still open, and not yet answered.
     * This is the single definition behind both the "new requests" list and its unread badge
     * (NotificationCountsService), so the badge can never count a request the list doesn't show.
     */
    public function newRequestsQuery(int $vendorId)
    {
        return RequestEligibleVendor::joinRequestCustomer()
            ->leftJoinCategoryToRequest()
            ->leftJoinCityCustomerToRequest()
            ->leftJoin('request_responses', function ($join) use ($vendorId) {
                $join->on('request_responses.request_id', '=', 'request_customers.id')
                    ->where('request_responses.vendor_id', '=', $vendorId);
            })
            ->where('request_eligible_vendors.vendor_id', $vendorId)
            ->where('request_customers.status', RequestCustomerStatusEnum::Open->value)
            ->whereNull('request_responses.id');
    }

    public function getNewRequests()
    {
        return $this->newRequestsQuery(getCurrVendorIdHelper())
            ->select(
                'request_customers.id as request_id',
                'categories.cat_name_ar',
                'request_customers.created_at as request_date',
                'cities.city_name_ar as city_customer_name_ar',
                'request_customers.description',
            )
            ->orderBy('request_customers.id', 'desc')
            ->paginate(10);
    }

    public function detailsRequestEligibleVendor(int $requestId)
    {
        return RequestEligibleVendor::joinRequestCustomer()
            ->leftJoinCategoryToRequest()
            ->leftJoinCityCustomerToRequest()
            ->where('request_eligible_vendors.request_id', $requestId)
            ->where('request_eligible_vendors.vendor_id', getCurrVendorIdHelper())
            ->where('request_customers.status', RequestCustomerStatusEnum::Open->value)
            ->select(
                'request_customers.id as request_id',
                'categories.cat_name_ar',
                'request_customers.created_at as request_date',
                'cities.city_name_ar as city_customer_name_ar',
                'request_customers.description',
                'request_customers.cities_ids_scope as cities',
            )
            ->first();
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

    public function getRequestImages($requestId)
    {
        return RequestImage::where('request_id', $requestId)->get(['image_name']);
    }
}
