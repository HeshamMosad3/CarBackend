<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestResponse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'request_id',
        'vendor_id',
        'status',
        'price',
        'note',
        'warranty',
    ];

    protected $casts = [
        'request_id' => 'integer',
        'price' => 'decimal:2',
        'status' => 'string',
    ];

    protected function requestDate(): Attribute
    {
        return Attribute::make(
            get: fn($value) =>
            $value
                ? Carbon::parse($value)->setTimezone(config('app.user_timezone'))->format('Y-m-d H:i')
                : null
        );
    }

    protected function responseDate(): Attribute
    {
        return Attribute::make(
            get: fn($value) =>
            $value
                ? Carbon::parse($value)->setTimezone(config('app.user_timezone'))->format('Y-m-d H:i')
                : null
        );
    }


    public function scopeJoinRequestCustomer($query)
    {
        return $query->join('request_customers', 'request_responses.request_id', '=', 'request_customers.id');
    }

    public function scopeLeftJoinCategoryToRequest($query)
    {
        return $query->leftJoin('categories', 'request_customers.category_id', '=', 'categories.id');
    }

    public function scopeLeftJoinCityCustomerToRequest($query)
    {
        return $query->leftJoin('cities', 'request_customers.customer_city_id', '=', 'cities.id');
    }

    public function scopeLeftJoinUserToRequest($query)
    {
        return $query->leftJoin('users', 'request_customers.user_id', '=', 'users.id');
    }

    public function scopeLeftJoinVendor($query)
    {
        return $query->leftJoin('vendors', 'request_responses.vendor_id', '=', 'vendors.id');
    }

    public function scopeLeftJoinVendorToUser($query)
    {
        return $query->leftJoin('users', 'vendors.user_id', '=', 'users.id');
    }

    public function scopeLeftJoinShippingRequest($query)
    {
        return $query->leftJoin('shipping_requests', function ($join) {
            // Only the latest shipment per response, so a canceled shipment followed by a new one
            // doesn't make the response show up twice.
            $join->on('request_responses.request_id', '=', 'shipping_requests.request_id')
                ->on('request_responses.id', '=', 'shipping_requests.response_id')
                ->whereRaw('shipping_requests.id = (select max(sr_latest.id) from shipping_requests sr_latest
                    where sr_latest.request_id = request_responses.request_id
                    and sr_latest.response_id = request_responses.id
                    and sr_latest.deleted_at is null)');
        });
    }

    protected function scopeSearchValueFilter($query, $value)
    {
        $query->when($value, function ($query, $value) {
            return $query->whereAny([
                'request_responses.id',
                'vendors.company_name_ar',
                'request_responses.created_at',
            ], 'like', '%' . $value . '%');
        });
    }
}
