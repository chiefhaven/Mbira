<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockAdjustmentCreatedOrModified
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $stockAdjustment;
    public $action;
    public $eisAdjustmentReason;
    public $eisTerminalId;
    public $eisSiteId;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(
        $stockAdjustment,
        $action,
        ?string $eisAdjustmentReason = null,
        ?int $eisTerminalId = null,
        ?string $eisSiteId = null
    )
    {
        $this->stockAdjustment = $stockAdjustment;
        $this->action = $action;
        $this->eisAdjustmentReason = $eisAdjustmentReason;
        $this->eisTerminalId = $eisTerminalId;
        $this->eisSiteId = $eisSiteId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
