<?php

namespace App\Services;

use App\Events\TripBoardUpdated;
use App\Models\ActivityLog;
use App\Models\User;

/**
 * R16: writes the trip's activity history and pushes the change to everyone
 * who has the board open.
 */
class TripActivityService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(int $budgetId, ?User $user, string $action, string $description, array $meta = []): ActivityLog
    {
        $log = new ActivityLog(['action' => $action, 'description' => $description, 'meta' => $meta ?: null]);
        $log->budget_id = $budgetId;
        $log->user()->associate($user);
        $log->save();

        $this->broadcast($budgetId, $user, $action, $description);

        return $log;
    }

    /**
     * Pushes a change that is not worth a history line (for example a vote taken back).
     */
    public function broadcast(int $budgetId, ?User $user, string $action, string $description): void
    {
        TripBoardUpdated::dispatch($budgetId, $action, $description, $user?->name);
    }
}
