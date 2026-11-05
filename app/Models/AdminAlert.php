<?php

namespace App\Models;

use App\Enums\AdminAlertType;
use Illuminate\Database\Eloquent\Model;

class AdminAlert extends Model
{
    protected $fillable = ['type', 'title', 'body', 'url', 'tone', 'data'];

    protected $casts = ['data' => 'array'];

    public function alertType(): ?AdminAlertType
    {
        return AdminAlertType::tryFrom($this->type);
    }

    /** The shape the dashboard's JavaScript works with. */
    public function toClient(): array
    {
        $type = $this->alertType();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tone' => $this->tone,
            'icon' => $type?->icon() ?? 'bi-bell',
            'urgent' => $type?->urgent() ?? false,
            'at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
