<?php

namespace App\Models;

use App\Enums\StatusUserEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_name_ar',
        'company_name_en',
        'description',
        'commercial_record',
        'date_expire_commercial_record',
        'phone_contact',
        'rating',
        'is_hide_phone_contact',
        'is_verified',
        'verification_notes',
        'verified_at',
        'store_name',
        'store_city',
        'store_address',
        'store_lat',
        'store_lng',
    ];

    protected $casts = [
        'store_lat' => 'double',
        'store_lng' => 'double',
        'rating' => 'float',
        'is_hide_phone_contact' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'date_expire_commercial_record' => 'date',
    ];

    protected $dates = ['verified_at', 'date_expire_commercial_record'];

    public function scopeJoinUsers($query)
    {
        return $query->join('users', 'vendors.user_id', '=', 'users.id');
    }

    public function scopeJoinVendorSpecialties($query)
    {
        return $query->join('vendor_specialties', 'vendors.id', '=', 'vendor_specialties.vendor_id');
    }

    public function scopeJoinVendorCities($query)
    {
        return $query->join('vendor_cities', 'vendors.id', '=', 'vendor_cities.vendor_id');
    }

    public function scopeLeftJoinVendorBrandCars($query, $categoryId)
    {
        return $query->leftJoin('vendor_brand_cars', function ($join) use ($categoryId) {
            $join->on('vendors.id', '=', 'vendor_brand_cars.vendor_id')
                ->where('vendor_brand_cars.category_id', '=', $categoryId);
        });
    }

    public function scopeWhereCategoryVendorSpecialty($query, $categoryId)
    {
        return $query->where('vendor_specialties.category_id', $categoryId);
    }

    public function scopeWhereInVendorCities($query, $citiesIdsScope)
    {
        return $query->whereIn('vendor_cities.city_id', (array) $citiesIdsScope);
    }

    public function scopeIsActive($query)
    {
        return $query->where('users.status', StatusUserEnum::Active->value);
    }

    public function scopeGetUserIdByVendorId($query, $vendorId)
    {
        return $query->where('id', $vendorId)->value('user_id') ?? 0;
    }
}
