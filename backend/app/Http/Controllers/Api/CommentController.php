<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\VoteRequest;
use App\Models\Booking;
use App\Models\Budget;
use App\Models\Comment;
use App\Models\Vote;
use App\Services\TripActivityService;
use App\Services\TripMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * R16: members comment and vote on itinerary items. Owners and editors take
 * part; viewers only read. View: ItineraryBoard.
 */
class CommentController extends Controller
{
    public function __construct(
        private TripMemberService $members,
        private TripActivityService $activity,
    ) {}

    public function store(StoreCommentRequest $request, Budget $budget, Booking $booking): JsonResponse
    {
        abort_unless((int) $booking->budget_id === (int) $budget->id, 404);
        Gate::allowIf($this->members->canEdit($budget, $request->user()));

        $comment = new Comment($request->validated());
        $comment->budget()->associate($budget);
        $comment->booking()->associate($booking);
        $comment->user()->associate($request->user());
        $comment->save();

        $this->activity->record($budget->id, $request->user(), 'comment.added', sprintf('commented on "%s": %s', $booking->title, Str::limit($comment->body, 80)), [
            'booking_id' => $booking->id, 'comment_id' => $comment->id,
        ]);

        return response()->json($comment->load('user:id,name'), 201);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $budget = $comment->budget;
        $isAuthor = (int) $comment->user_id === (int) $request->user()->id;
        Gate::allowIf($isAuthor || $this->members->canManageMembers($budget, $request->user()));

        $title = $comment->booking?->title;
        $comment->delete();

        $this->activity->record($budget->id, $request->user(), 'comment.deleted', "deleted a comment on \"{$title}\"");

        return response()->json(['message' => 'Comment deleted']);
    }

    public function vote(VoteRequest $request, Budget $budget, Booking $booking): JsonResponse
    {
        abort_unless((int) $booking->budget_id === (int) $budget->id, 404);
        Gate::allowIf($this->members->canEdit($budget, $request->user()));
        $value = (int) $request->validated('value');
        $user = $request->user();

        $current = Vote::where('booking_id', $booking->id)->where('user_id', $user->id)->first();

        if ($value === 0) {
            $current?->delete();
            $this->activity->broadcast($budget->id, $user, 'vote.removed', "took back their vote on \"{$booking->title}\"");
        } elseif ($current?->value !== $value) {
            $vote = $current ?? new Vote;
            $vote->value = $value;
            $vote->booking()->associate($booking);
            $vote->user()->associate($user);
            $vote->save();

            $this->activity->record($budget->id, $user, 'vote.cast', ($value === 1 ? 'voted for' : 'voted against')." \"{$booking->title}\"", ['booking_id' => $booking->id]);
        }

        $votes = Vote::where('booking_id', $booking->id)->pluck('value');

        return response()->json([
            'up' => $votes->filter(fn (int $v): bool => $v === 1)->count(),
            'down' => $votes->filter(fn (int $v): bool => $v === -1)->count(),
            'mine' => $value,
        ]);
    }
}
