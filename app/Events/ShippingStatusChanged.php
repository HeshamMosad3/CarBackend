<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the apps that a shipment changed status, so the "what do I have to do next" text and the
 * chat buttons update on their own. It carries ids only; the app re-reads the shipment itself.
 */
class ShippingStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array<int> $userIds users whose private `user.{id}` channel should hear it */
    public function __construct(
        public array $userIds,
        public int $shippingId,
        public int $requestId,
        public int $responseId,
        public string $status,
    ) {}

    public function broadcastOn(): array
    {
        return array_map(fn ($id) => new PrivateChannel("user.{$id}"), $this->userIds);
    }

    public function broadcastAs(): string
    {
        return 'shipping.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'shipping_id' => $this->shippingId,
            'request_id' => $this->requestId,
            'response_id' => $this->responseId,
            'status' => $this->status,
        ];
    }
}
