<?php

namespace App\Services;

use App\Mail\TripInviteMail;
use App\Models\Budget;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * R16: who may see and change a group trip, and the invite / join flow.
 * The budget's creator is the owner; others get owner, editor or viewer.
 */
class TripMemberService
{
    public function __construct(private TripActivityService $activity) {}

    /**
     * @return 'owner'|'editor'|'viewer'|null
     */
    public function roleFor(Budget $budget, ?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ((int) $budget->user_id === (int) $user->id) {
            return 'owner';
        }

        return TripMember::active()->where('budget_id', $budget->id)->where('user_id', $user->id)->value('role');
    }

    public function canView(Budget $budget, ?User $user): bool
    {
        return $this->roleFor($budget, $user) !== null;
    }

    /**
     * Owners and editors change the itinerary, vote and comment; viewers only look.
     */
    public function canEdit(Budget $budget, ?User $user): bool
    {
        return in_array($this->roleFor($budget, $user), TripMember::EDIT_ROLES, true);
    }

    public function canManageMembers(Budget $budget, ?User $user): bool
    {
        return $this->roleFor($budget, $user) === 'owner';
    }

    /**
     * Trips the user owns or has joined, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function tripsFor(User $user): array
    {
        $memberships = TripMember::active()->where('user_id', $user->id)->pluck('role', 'budget_id');

        $budgets = Budget::query()
            ->where('user_id', $user->id)
            ->orWhereIn('id', $memberships->keys())
            ->with(['destination:id,name,country', 'user:id,name'])
            ->latest()
            ->latest('id')
            ->get();

        $counts = TripMember::active()->whereIn('budget_id', $budgets->pluck('id'))
            ->selectRaw('budget_id, count(*) as total')->groupBy('budget_id')->pluck('total', 'budget_id');

        return $budgets->map(fn (Budget $budget): array => [
            'id' => $budget->id,
            'title' => $budget->title,
            'days' => $budget->days,
            'travelers' => $budget->travelers,
            'destination' => $budget->destination?->only(['name', 'country']),
            'owner' => $budget->user?->name,
            'role' => (int) $budget->user_id === (int) $user->id ? 'owner' : $memberships[$budget->id],
            'members_count' => 1 + (int) ($counts[$budget->id] ?? 0),
        ])->values()->all();
    }

    /**
     * Invites someone by email, or makes a one-time invite link when there is no email.
     * Inviting the same email again replaces the pending invite.
     */
    public function invite(Budget $budget, User $by, ?string $email, string $role): TripMember
    {
        if ($email !== null) {
            $email = Str::lower($email);
            $this->ensureNotOnTrip($budget, $email);
        }

        $invite = $email !== null
            ? TripMember::pending()->where('budget_id', $budget->id)->where('email', $email)->first()
            : null;
        $invite ??= new TripMember(['email' => $email]);

        $invite->fill([
            'role' => $role,
            'status' => 'invited',
            'invite_token' => Str::random(40),
            'expires_at' => now()->addDays(TripMember::INVITE_DAYS),
        ]);
        $invite->budget()->associate($budget);
        $invite->inviter()->associate($by);
        $invite->save();

        if ($email !== null) {
            Mail::to($email)->send(new TripInviteMail($invite->load(['budget.destination', 'inviter']), $this->inviteUrl($invite)));
            $this->activity->record($budget->id, $by, 'member.invited', "invited {$email} as {$role}", ['member_id' => $invite->id]);
        } else {
            $this->activity->record($budget->id, $by, 'member.invited', "created an invite link for a new {$role}", ['member_id' => $invite->id]);
        }

        return $invite;
    }

    /**
     * The invite for this token, if it is still waiting to be used.
     */
    public function findInvite(string $token): ?TripMember
    {
        return TripMember::pending()->where('invite_token', $token)->with(['budget.destination', 'inviter'])->first();
    }

    /**
     * Turns an invite into a membership for the user who opened it.
     *
     * @return array{member: TripMember, already_member: bool}
     */
    public function accept(TripMember $invite, User $user): array
    {
        $budget = $invite->budget;

        if ($invite->hasExpired()) {
            throw new HttpException(410, 'This invite has expired. Ask the trip owner for a new one.');
        }

        if ($invite->email !== null && Str::lower($invite->email) !== Str::lower($user->email)) {
            throw new HttpException(403, 'This invite was sent to another email address. Log in with that address to join.');
        }

        if ((int) $budget->user_id === (int) $user->id) {
            throw ValidationException::withMessages(['invite' => 'You already own this trip.']);
        }

        $existing = TripMember::active()->where('budget_id', $budget->id)->where('user_id', $user->id)->first();
        if ($existing !== null) {
            $invite->delete();

            return ['member' => $existing, 'already_member' => true];
        }

        $invite->fill(['status' => 'active', 'email' => Str::lower($user->email), 'invite_token' => null, 'expires_at' => null, 'joined_at' => now()]);
        $invite->user()->associate($user);
        $invite->save();

        $this->activity->record($budget->id, $user, 'member.joined', "joined as {$invite->role}", ['member_id' => $invite->id]);

        return ['member' => $invite, 'already_member' => false];
    }

    public function changeRole(TripMember $member, User $by, string $role): TripMember
    {
        $member->update(['role' => $role]);

        $this->activity->record($member->budget_id, $by, 'member.role_changed', "changed {$this->nameOf($member)}'s role to {$role}", ['member_id' => $member->id]);

        return $member;
    }

    /**
     * An owner removing someone (or cancelling an invite), or a member leaving.
     */
    public function remove(TripMember $member, User $by): void
    {
        $leaving = (int) $member->user_id === (int) $by->id;
        $description = match (true) {
            $leaving => 'left the trip',
            ! $member->isActive() => $member->email !== null ? "cancelled the invite for {$member->email}" : 'cancelled an invite link',
            default => "removed {$this->nameOf($member)} from the trip",
        };

        $budgetId = $member->budget_id;
        $member->delete();

        $this->activity->record($budgetId, $by, $leaving ? 'member.left' : 'member.removed', $description);
    }

    /**
     * Everyone on the trip; pending invites (with their links) only for owners.
     *
     * @return array{my_role: string|null, owner: array<string, mixed>, members: list<array<string, mixed>>, invites: list<array<string, mixed>>}
     */
    public function membersOf(Budget $budget, User $viewer): array
    {
        $role = $this->roleFor($budget, $viewer);
        $budget->loadMissing('user:id,name,email');

        $members = TripMember::active()->where('budget_id', $budget->id)->with('user:id,name,email')->orderBy('joined_at')->get()
            ->map(fn (TripMember $member): array => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'role' => $member->role,
                'joined_at' => $member->joined_at?->toIso8601String(),
            ])->values()->all();

        $invites = $role === 'owner'
            ? TripMember::pending()->where('budget_id', $budget->id)->with('inviter:id,name')->latest()->get()
                ->map(fn (TripMember $invite): array => [
                    'id' => $invite->id,
                    'email' => $invite->email,
                    'role' => $invite->role,
                    'link' => $this->inviteUrl($invite),
                    'invited_by' => $invite->inviter?->name,
                    'expires_at' => $invite->expires_at?->toIso8601String(),
                    'expired' => $invite->hasExpired(),
                ])->values()->all()
            : [];

        return [
            'my_role' => $role,
            'owner' => ['user_id' => $budget->user_id, 'name' => $budget->user?->name, 'email' => $budget->user?->email, 'role' => 'owner'],
            'members' => $members,
            'invites' => $invites,
        ];
    }

    public function inviteUrl(TripMember $invite): string
    {
        return rtrim((string) config('voyenta.frontend_url'), '/').'/join/'.$invite->invite_token;
    }

    private function ensureNotOnTrip(Budget $budget, string $email): void
    {
        $budget->loadMissing('user:id,email');
        $onTrip = Str::lower((string) $budget->user?->email) === $email
            || TripMember::active()->where('budget_id', $budget->id)->whereHas('user', fn ($query) => $query->whereRaw('lower(email) = ?', [$email]))->exists();

        if ($onTrip) {
            throw ValidationException::withMessages(['email' => 'This person is already on the trip.']);
        }
    }

    private function nameOf(TripMember $member): string
    {
        return $member->user?->name ?? $member->email ?? 'a member';
    }
}
