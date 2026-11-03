<?php

namespace App\Http\Repositories\Dashboard\RequestsManagement;

use App\Enums\RequestCustomerStatusEnum;
use App\Models\BrandCar;
use App\Models\City;
use App\Models\CustomField;
use App\Models\RequestBrandScope;
use App\Models\RequestCustomer;
use App\Models\RequestCustomFieldValue;
use App\Models\RequestImage;
use App\Models\RequestResponse;
use App\Models\ShippingRequest;
use App\Traits\HandlesDatatablesTrait;
use Illuminate\Http\Request;

class RequestsManagementRepository
{
    use HandlesDatatablesTrait;

    public function index(Request $request, $searchValue)
    {
        $query = $this->filteredQuery($request, $searchValue)
            ->selectRaw(
                'request_customers.id as request_id,
                request_customers.status as request_status,
                categories.cat_name_ar,
                request_customers.created_at as request_date,
                cities.city_name_ar as city_customer_name_ar,
                users.name as customer_name,
                (SELECT COUNT(id) FROM request_responses WHERE request_responses.request_id = request_customers.id AND request_responses.deleted_at IS NULL) as count_response
                ',
            );

        return $this->paginateRecordsForDatatables($request, $query);
    }

    public function recordsCountRequestsCustomerWithFilter($searchValue, ?Request $request = null)
    {
        return $this->filteredQuery($request, $searchValue)->count('request_customers.id');
    }

    /** List query shared by the rows and the filtered count, so the pager always matches the rows. */
    private function filteredQuery(?Request $request, $searchValue)
    {
        $status = $request ? RequestCustomerStatusEnum::tryFrom((string) $request->input('status')) : null;

        return RequestCustomer::leftJoinCity()
            ->leftJoinCategory()
            ->leftJoinUser()
            ->when($status, fn ($q) => $q->where('request_customers.status', $status->value))
            ->when($searchValue, function ($q, $value) {
                $q->whereAny([
                    'request_customers.id',
                    'categories.cat_name_ar',
                    'cities.city_name_ar',
                    'users.name',
                    'users.phone',
                    'request_customers.description',
                ], 'like', '%' . $value . '%');
            });
    }

    public function statusCounts(): array
    {
        return RequestCustomer::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    public function getRequestById(int $requestId)
    {
        return RequestCustomer::leftJoinCategory()
            ->leftJoinCity()
            ->leftJoinUser()
            ->where('request_customers.id', $requestId)
            ->select(
                'request_customers.id as request_id',
                'request_customers.user_id',
                'categories.cat_name_ar',
                'request_customers.created_at as request_date',
                'cities.city_name_ar as city_customer_name_ar',
                'request_customers.description',
                'request_customers.cities_ids_scope as cities',
                'request_customers.status as request_status',
                'users.name as user_name',
                'users.phone as user_phone',
                'users.logo as user_logo',
                'users.status as user_status',
            )
            ->first();
    }

    public function getRequestBrandNamesScope($requestId)
    {
        $brandIdsScope = RequestBrandScope::where('request_id', $requestId)->first(['brand_ids_scope']);
        if (!$brandIdsScope || empty($brandIdsScope->brand_ids_scope)) {
            return [];
        }

        $brandCarsCached = BrandCar::getBrandCarsCached();

        $brandsNames = [];
        foreach ($brandIdsScope->brand_ids_scope as $brand) {
            $brandsNames[] = $brandCarsCached->where('id', (int) $brand)->value('brand_name_ar') ?? '';
        }

        return array_values(array_filter($brandsNames));
    }

    public function getRequestCitiesNamesScope($cityIdsScope)
    {
        $ids = is_array($cityIdsScope) ? $cityIdsScope : json_decode((string) $cityIdsScope, true);
        if (empty($ids)) {
            return [];
        }

        $citiesCached = City::getCitiesCached();

        $citiesNames = [];
        foreach ($ids as $city) {
            $citiesNames[] = $citiesCached->where('id', (int) $city)->value('city_name_ar') ?? '';
        }

        return array_values(array_filter($citiesNames));
    }

    public function getRequestImages($requestId)
    {
        return RequestImage::where('request_id', $requestId)->get(['image_name']);
    }

    public function getRequestCustomFields($requestId)
    {
        $requestCustomFields = RequestCustomFieldValue::where('request_id', $requestId)->get();
        $customFieldsCached = CustomField::getCustomFieldsCached();

        $result = [];
        foreach ($requestCustomFields as $item) {
            $value = json_decode($item->value);
            $result[] = [
                'key' => $customFieldsCached->where('id', $item->custom_field_id)->value('label_ar') ?? '',
                'value' => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            ];
        }

        return $result;
    }

    /** Every vendor offer on the request, cheapest available first. */
    public function getRequestResponses($requestId)
    {
        return RequestResponse::query()
            ->leftJoinVendor()
            ->where('request_responses.request_id', $requestId)
            ->select(
                'request_responses.id',
                'request_responses.status',
                'request_responses.price',
                'request_responses.warranty',
                'request_responses.note',
                'request_responses.created_at',
                'vendors.company_name_ar',
                'vendors.user_id as vendor_user_id',
            )
            ->orderByRaw("CASE WHEN request_responses.status = 'unavailable' THEN 1 ELSE 0 END")
            ->orderBy('request_responses.price')
            ->get();
    }

    public function getRequestShipments($requestId)
    {
        return ShippingRequest::where('request_id', $requestId)
            ->orderByDesc('id')
            ->get(['id', 'response_id', 'status', 'payment_status', 'created_at']);
    }
}
