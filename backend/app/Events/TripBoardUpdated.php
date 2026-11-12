<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * R16: tells everyone with a group trip's board open that something changed,
 * so their board reloads at once (Laravel Reverb). ShouldRescue keeps the
 * request working when the Reverb server is not running.
 */
class TripBoardUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;

    public function __construct(
        public int $budgetId,
        public string $action,
        public string $description,
        public ?string $byName,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("trips.{$this->budgetId}")];
    }

    public function broadcastAs(): string
    {
        return 'board.updated';
    }

    /**
     * @return array{action: string, description: string, by: string|null}
     */
    public function broadcastWith(): array
    {
        return ['action' => $this->action, 'description' => $this->description, 'by' => $this->byName];
    }
}
