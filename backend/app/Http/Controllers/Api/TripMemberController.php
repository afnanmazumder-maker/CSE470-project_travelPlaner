<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteTripMemberRequest;
use App\Http\Requests\UpdateTripMemberRequest;
use App\Models\Budget;
use App\Models\TripMember;
use App\Services\TripMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * R16: group trip members. Owners invite by email or link as owner, editor
 * or viewer, change roles and remove people; members can leave.
 * Views: CollaboratorsPanel (on the ItineraryBoard page), JoinTrip.
 */
class TripMemberController extends Controller
{
    public function __construct(private TripMemberService $members) {}

    public function trips(Request $request): JsonResponse
    {
        $trips = $this->members->tripsFor($request->user());

        if ($request->boolean('can_edit')) {
            $trips = array_values(array_filter($trips, fn (array $trip): bool => in_array($trip['role'], TripMember::EDIT_ROLES, true)));
        }

        return response()->json($trips);
    }

    public function index(Request $request, Budget $budget): JsonResponse
    {
        Gate::allowIf($this->members->canView($budget, $request->user()));

        return response()->json($this->members->membersOf($budget, $request->user()));
    }

    public function store(InviteTripMemberRequest $request, Budget $budget): JsonResponse
    {
        Gate::allowIf($this->members->canManageMembers($budget, $request->user()));
        $data = $request->validated();

        $invite = $this->members->invite($budget, $request->user(), $data['email'] ?? null, $data['role']);

        return response()->json([
            'invite' => ['id' => $invite->id, 'email' => $invite->email, 'role' => $invite->role, 'link' => $this->members->inviteUrl($invite)],
            'members' => $this->members->membersOf($budget, $request->user()),
        ], 201);
    }

    public function update(UpdateTripMemberRequest $request, Budget $budget, TripMember $member): JsonResponse
    {
        abort_unless((int) $member->budget_id === (int) $budget->id, 404);
        Gate::allowIf($this->members->canManageMembers($budget, $request->user()));

        $this->members->changeRole($member, $request->user(), $request->validated('role'));

        return response()->json($this->members->membersOf($budget, $request->user()));
    }

    public function destroy(Request $request, Budget $budget, TripMember $member): JsonResponse
    {
        abort_unless((int) $member->budget_id === (int) $budget->id, 404);
        $leaving = (int) $member->user_id === (int) $request->user()->id;
        Gate::allowIf($leaving || $this->members->canManageMembers($budget, $request->user()));

        $this->members->remove($member, $request->user());

        return response()->json(['message' => $leaving ? 'You left the trip.' : 'Removed.']);
    }

    public function showInvite(string $token): JsonResponse
    {
        $invite = $this->members->findInvite($token);
        abort_if($invite === null, 404, 'This invite link is not valid any more.');

        return response()->json([
            'trip' => [
                'title' => $invite->budget->title,
                'days' => $invite->budget->days,
                'destination' => $invite->budget->destination?->only(['name', 'country']),
            ],
            'role' => $invite->role,
            'email' => $invite->email,
            'invited_by' => $invite->inviter?->name,
            'expired' => $invite->hasExpired(),
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invite = $this->members->findInvite($token);
        abort_if($invite === null, 404, 'This invite link is not valid any more.');

        $result = $this->members->accept($invite, $request->user());

        return response()->json([
            'trip_id' => $result['member']->budget_id,
            'role' => $result['member']->role,
            'already_member' => $result['already_member'],
        ]);
    }
}
