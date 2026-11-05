<?php

namespace App\Models;

use App\Enums\StatusShippingRequestEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ShippingRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'request_id',
        'response_id',
        'order_number',
        'name_origin_vendor',
        'city_origin_vendor',
        'address_origin_vendor',
        'lat_origin_vendor',
        'lng_origin_vendor',
        'phone_origin_vendor',
        'length',
        'width',
        'height',
        'weight',
        'packages',
        'id_number_user',
        'city_origin_dimensions',
        'address_origin_dimensions',
        'phone_origin_dimensions',
        'status',
        'fee_cheapest_shipping',
        'amount_rate_app',
        'is_user_confirmed',
        'oto_id',
        'shipping_cost',
        'cost_sent_at',
        'payment_due_at',
        'payment_reminded_at',
        'payment_status',
        'paid_at',
        'refunded_at',
        'refund_note',
        'carrier_name',
        'tracking_number',
        'pickup_details',
        'pickup_method',
        'oto_delivery_option_id',
        'carrier_pickup_support',
        'name_destination',
        'lat_destination',
        'lng_destination',
        'admin_notes',
        'cancel_reason',
        'status_changed_at',
        'active_key',
        'waybill_file',
    ];

    /** Private-disk folder holding the carrier waybills. They are only ever served through authenticated routes. */
    public const WAYBILL_DIR = 'shipping-waybills';

    /** 'pdf', 'image', or null when no waybill was uploaded. */
    public function waybillType(): ?string
    {
        if (!$this->waybill_file) {
            return null;
        }

        return strtolower(pathinfo($this->waybill_file, PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'image';
    }

    protected static function booted(): void
    {
        // Keep the "one open shipment per response" key in step with the status. Legacy rows are
        // left out on purpose: they predate the guard and must not block vendors from re-sending.
        static::saving(function (self $shippingRequest) {
            $status = $shippingRequest->status;
            $shippingRequest->active_key = ($status instanceof StatusShippingRequestEnum && !$status->isTerminal())
                ? $shippingRequest->request_id . '-' . $shippingRequest->response_id
                : null;
        });
    }

    /** The open new-flow shipment for a request/response pair, if any. */
    public static function findOpenFor(int $requestId, int $responseId): ?self
    {
        return self::where('active_key', $requestId . '-' . $responseId)->first();
    }

    protected $casts = [
        'length' => 'double',
        'width' => 'double',
        'height' => 'double',
        'weight' => 'double',
        'fee_cheapest_shipping' => 'double',
        'amount_rate_app' => 'double',
        'is_user_confirmed' => 'boolean',
        'status' => StatusShippingRequestEnum::class,
        'packages' => 'array',
        'shipping_cost' => 'double',
        'lat_destination' => 'double',
        'lng_destination' => 'double',
        'cost_sent_at' => 'datetime',
        'payment_due_at' => 'datetime',
        'payment_reminded_at' => 'datetime',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'status_changed_at' => 'datetime',
    ];

    protected function shippingRequestDate(): Attribute
    {
        return Attribute::make(
            get: fn($value) =>
            $value
                ? Carbon::parse($value)->setTimezone(config('app.user_timezone'))->format('Y-m-d H:i')
                : null
        );
    }

    protected function scopeSearchValueFilter($query, $value)
    {
        $query->when($value, function ($query, $value) {
            return $query->whereAny([
                'shipping_requests.id',
                'shipping_requests.request_id',
                'shipping_requests.response_id',
                'shipping_requests.city_origin_vendor',
                'shipping_requests.city_origin_dimensions',
                'shipping_requests.created_at',
            ], 'like', '%' . $value . '%');
        });
    }

    protected function scopeConfirmShippingFilter($query, $value)
    {
        return $query->when($value, function ($query, $value) {
            return $query->where('shipping_requests.is_user_confirmed', $value);
        });
    }
}
