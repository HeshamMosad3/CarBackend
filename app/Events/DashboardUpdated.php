<?php

namespace App\Events;

use App\Support\Dashboard\AdminAlerts;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * "Something the admin dashboard shows has changed" — sent to every open dashboard so it can update
 * itself without a refresh. It may also carry an alert (something that needs the admin to act).
 */
class DashboardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string     $entity  What changed: request, response, shipping, complaint, vendor, user, category, alert
     * @param string     $action  created, updated, deleted, restored or alert
     * @param array|null $alert   AdminAlert::toClient(), when an alert was raised
     * @param array      $counts  The "needs your attention" counts after the change
     */
    public function __construct(
        public string $entity,
        public string $action,
        public $id,
        public ?array $alert,
        public array $counts,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel(AdminAlerts::CHANNEL)];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'entity' => $this->entity,
            'action' => $this->action,
            'id' => $this->id,
            'alert' => $this->alert,
            'counts' => $this->counts,
        ];
    }
}
