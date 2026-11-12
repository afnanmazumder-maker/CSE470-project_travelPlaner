<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Budget;
use App\Models\Comment;
use App\Models\TripMember;
use App\Models\User;
use App\Models\Vote;

/**
 * R16: the shared itinerary of a group trip. Its items are the bookings saved
 * to the trip (flights, stays and activities), each with votes and comments.
 */
class ItineraryBoardService
{
    public const ACTIVITY_LIMIT = 50;

    public function __construct(private TripMemberService $members) {}

    /**
     * @return array<string, mixed>
     */
    public function board(Budget $budget, User $viewer): array
    {
        $role = $this->members->roleFor($budget, $viewer);
        $canEdit = in_array($role, TripMember::EDIT_ROLES, true);
        $budget->loadMissing(['destination:id,name,country', 'user:id,name']);

        $items = Booking::where('budget_id', $budget->id)->with('user:id,name')->orderBy('start_at')->orderBy('id')->get();
        $votes = Vote::whereIn('booking_id', $items->pluck('id'))->get()->groupBy('booking_id');
        $comments = Comment::whereIn('booking_id', $items->pluck('id'))->with('user:id,name')->orderBy('created_at')->orderBy('id')->get()->groupBy('booking_id');

        return [
            'trip' => [
                'id' => $budget->id,
                'title' => $budget->title,
                'days' => $budget->days,
                'travelers' => $budget->travelers,
                'destination' => $budget->destination?->only(['name', 'country']),
                'owner' => $budget->user?->name,
            ],
            'my_role' => $role,
            'can_edit' => $canEdit,
            'members_count' => 1 + TripMember::active()->where('budget_id', $budget->id)->count(),
            'items' => $items->map(function (Booking $item) use ($votes, $comments, $viewer, $canEdit, $role): array {
                $itemVotes = $votes->get($item->id, collect());

                return [
                    'id' => $item->id,
                    'type' => $item->type,
                    'title' => $item->title,
                    'provider' => $item->provider,
                    'confirmation_number' => $item->confirmation_number,
                    'start_at' => $item->start_at?->format('Y-m-d\TH:i'),
                    'end_at' => $item->end_at?->format('Y-m-d\TH:i'),
                    'status' => $item->status,
                    'price' => $item->price,
                    'party_size' => $item->party_size,
                    'notes' => $item->notes,
                    'added_by' => $item->user?->only(['id', 'name']),
                    'can_edit' => $canEdit,
                    'votes' => [
                        'up' => $itemVotes->where('value', 1)->count(),
                        'down' => $itemVotes->where('value', -1)->count(),
                        'mine' => (int) ($itemVotes->firstWhere('user_id', $viewer->id)?->value ?? 0),
                    ],
                    'comments' => $comments->get($item->id, collect())->map(fn (Comment $comment): array => [
                        'id' => $comment->id,
                        'body' => $comment->body,
                        'user' => $comment->user?->only(['id', 'name']),
                        'created_at' => $comment->created_at?->toIso8601String(),
                        'can_delete' => (int) $comment->user_id === (int) $viewer->id || $role === 'owner',
                    ])->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * The newest lines of the trip's history.
     *
     * @return list<array<string, mixed>>
     */
    public function activity(Budget $budget): array
    {
        return ActivityLog::where('budget_id', $budget->id)->with('user:id,name')
            ->latest()->latest('id')->limit(self::ACTIVITY_LIMIT)->get()
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user?->only(['id', 'name']),
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values()->all();
    }
}
