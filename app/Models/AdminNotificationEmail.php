<?php

namespace App\Models;

use App\Enums\AdminAlertType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An address that receives the dashboard's "action needed" emails. The admin chooses, per address,
 * whether it is on at all and which kinds of alert it gets.
 */
class AdminNotificationEmail extends Model
{
    use HasFactory;

    protected $fillable = ['email', 'label', 'is_active', 'events'];

    protected $casts = [
        'is_active' => 'boolean',
        'events' => 'array',
    ];

    /** Alert types this address receives. NULL (older rows) means every type. */
    public function subscribedTypes(): array
    {
        return $this->events === null ? AdminAlertType::keys() : array_values(array_intersect($this->events, AdminAlertType::keys()));
    }

    public function wants(AdminAlertType $type): bool
    {
        return $this->is_active && in_array($type->value, $this->subscribedTypes(), true);
    }
}
