<?php

namespace Tests\Feature;

use App\Events\TripBoardUpdated;
use App\Mail\TripInviteMail;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Budget;
use App\Models\Destination;
use App\Models\TripMember;
use App\Models\User;
use App\Services\TripMemberService;
use Database\Seeders\DestinationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TripCollaborationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Budget $trip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DestinationSeeder::class);
        $this->owner = User::factory()->create(['name' => 'Afnan', 'email' => 'afnan@example.com']);
        $this->trip = $this->tripFor($this->owner, 'Bali with friends');
    }

    private function tripFor(User $user, string $title): Budget
    {
        return $user->budgets()->create([
            'title' => $title, 'destination_id' => Destination::where('name', 'Bali')->value('id'),
            'days' => 5, 'travelers' => 4, 'comfort' => 'moderate', 'planned' => ['stay' => 600],
        ]);
    }

    private function member(string $role, ?Budget $trip = null): User
    {
        $member = TripMember::factory()->for($trip ?? $this->trip)->role($role)->create();

        return $member->user;
    }

    private function item(array $overrides = []): Booking
    {
        $booking = new Booking(array_merge([
            'type' => 'stay', 'title' => 'Ubud Rice Terrace Villa', 'start_at' => '2026-12-01 15:00',
            'end_at' => '2026-12-04 11:00', 'status' => 'planned', 'price' => 540,
        ], $overrides));
        $booking->budget()->associate($this->trip);
        $booking->user()->associate($this->owner);
        $booking->save();

        return $booking;
    }

    /**
     * @return array<string, array{string|null, bool, bool, bool}>
     */
    public static function roles(): array
    {
        return [
            'owner' => ['owner', true, true, true],
            'editor' => ['editor', true, true, false],
            'viewer' => ['viewer', true, false, false],
            'not on the trip' => [null, false, false, false],
        ];
    }

    #[DataProvider('roles')]
    public function test_each_role_can_view_edit_and_manage_as_expected(?string $role, bool $view, bool $edit, bool $manage): void
    {
        $user = $role === null ? User::factory()->create() : $this->member($role);
        $access = app(TripMemberService::class);

        $this->assertSame($role, $access->roleFor($this->trip, $user));
        $this->assertSame($view, $access->canView($this->trip, $user));
        $this->assertSame($edit, $access->canEdit($this->trip, $user));
        $this->assertSame($manage, $access->canManageMembers($this->trip, $user));
    }

    public function test_the_trip_creator_is_the_owner_without_a_member_row(): void
    {
        $this->assertSame('owner', app(TripMemberService::class)->roleFor($this->trip, $this->owner));
        $this->assertDatabaseCount('trip_members', 0);
    }

    public function test_r16_routes_require_login(): void
    {
        $this->getJson('/api/trips')->assertUnauthorized();
        $this->getJson("/api/budgets/{$this->trip->id}/board")->assertUnauthorized();
        $this->postJson("/api/budgets/{$this->trip->id}/invites", ['role' => 'editor'])->assertUnauthorized();
    }

    public function test_owner_invites_by_email_and_the_email_has_the_join_link(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/budgets/{$this->trip->id}/invites", ['email' => 'Sara@Example.com', 'role' => 'editor'])
            ->assertCreated()
            ->assertJsonPath('invite.email', 'sara@example.com')
            ->assertJsonPath('members.invites.0.role', 'editor');

        $link = $response->json('invite.link');
        $this->assertStringStartsWith(config('voyenta.frontend_url').'/join/', $link);
        Mail::assertSent(TripInviteMail::class, fn (TripInviteMail $mail): bool => $mail->hasTo('sara@example.com') && $mail->joinUrl === $link);
        $this->assertDatabaseHas('activity_logs', ['budget_id' => $this->trip->id, 'user_id' => $this->owner->id, 'description' => 'invited sara@example.com as editor']);
    }

    public function test_inviting_the_same_email_again_replaces_the_pending_invite(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->owner);

        $first = $this->postJson("/api/budgets/{$this->trip->id}/invites", ['email' => 'sara@example.com', 'role' => 'editor'])->json('invite.link');
        $this->postJson("/api/budgets/{$this->trip->id}/invites", ['email' => 'sara@example.com', 'role' => 'viewer'])->assertCreated();

        $this->assertSame(1, TripMember::pending()->count());
        $this->assertSame('viewer', TripMember::pending()->value('role'));
        $this->getJson('/api/invites/'.basename($first))->assertNotFound();
    }

    public function test_invite_validation(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/budgets/{$this->trip->id}/invites", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role' => 'The role field is required.']);
        $this->postJson("/api/budgets/{$this->trip->id}/invites", ['email' => 'not-an-email', 'role' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'role' => 'The selected role is invalid.']);
    }

    public function test_people_already_on_the_trip_cannot_be_invited(): void
    {
        $editor = $this->member('editor');
        Sanctum::actingAs($this->owner);

        foreach (['afnan@example.com', strtoupper($editor->email)] as $email) {
            $this->postJson("/api/budgets/{$this->trip->id}/invites", ['email' => $email, 'role' => 'viewer'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['email' => 'This person is already on the trip.']);
        }
    }

    public function test_only_owners_can_invite(): void
    {
        Sanctum::actingAs($this->member('editor'));

        $this->postJson("/api/budgets/{$this->trip->id}/invites", ['role' => 'viewer'])->assertForbidden();
        $this->assertDatabaseCount('trip_members', 1);
    }

    public function test_an_invite_link_shows_the_trip_and_lets_one_person_join(): void
    {
        Sanctum::actingAs($this->owner);
        $token = basename($this->postJson("/api/budgets/{$this->trip->id}/invites", ['role' => 'editor'])->json('invite.link'));

        $this->getJson("/api/invites/{$token}")
            ->assertOk()
            ->assertJsonPath('trip.title', 'Bali with friends')
            ->assertJsonPath('role', 'editor')
            ->assertJsonPath('invited_by', 'Afnan');

        $sara = User::factory()->create(['name' => 'Sara']);
        Sanctum::actingAs($sara);
        $this->postJson("/api/invites/{$token}/accept")
            ->assertOk()
            ->assertJsonPath('trip_id', $this->trip->id)
            ->assertJsonPath('already_member', false);

        $this->assertSame('editor', app(TripMemberService::class)->roleFor($this->trip, $sara));
        $this->assertDatabaseHas('activity_logs', ['user_id' => $sara->id, 'action' => 'member.joined', 'description' => 'joined as editor']);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/invites/{$token}/accept")->assertNotFound()->assertJsonPath('message', 'This invite link is not valid any more.');
    }

    public function test_an_email_invite_only_works_for_that_email(): void
    {
        $invite = TripMember::factory()->for($this->trip)->invited('sara@example.com')->create();

        Sanctum::actingAs(User::factory()->create(['email' => 'someone@example.com']));
        $this->postJson("/api/invites/{$invite->invite_token}/accept")
            ->assertForbidden()
            ->assertJsonPath('message', 'This invite was sent to another email address. Log in with that address to join.');

        Sanctum::actingAs(User::factory()->create(['email' => 'SARA@example.com']));
        $this->postJson("/api/invites/{$invite->invite_token}/accept")->assertOk()->assertJsonPath('role', 'editor');
    }

    public function test_an_expired_invite_cannot_be_used(): void
    {
        $this->freezeTime();
        $invite = TripMember::factory()->for($this->trip)->invited('sara@example.com')->create();
        $this->travel(TripMember::INVITE_DAYS + 1)->days();

        Sanctum::actingAs(User::factory()->create(['email' => 'sara@example.com']));
        $this->postJson("/api/invites/{$invite->invite_token}/accept")
            ->assertStatus(410)
            ->assertJsonPath('message', 'This invite has expired. Ask the trip owner for a new one.');
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_the_owner_cannot_join_their_own_trip_and_a_member_joining_twice_keeps_one_row(): void
    {
        $link = TripMember::factory()->for($this->trip)->invited()->create(['email' => null]);
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/invites/{$link->invite_token}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invite' => 'You already own this trip.']);

        $editor = $this->member('editor');
        Sanctum::actingAs($editor);
        $this->postJson("/api/invites/{$link->invite_token}/accept")->assertOk()->assertJsonPath('already_member', true);
        $this->assertSame(1, TripMember::where('user_id', $editor->id)->count());
    }

    public function test_board_shows_items_votes_and_comments_to_members_only(): void
    {
        $viewer = $this->member('viewer');
        $item = $this->item();

        Sanctum::actingAs($viewer);
        $this->getJson("/api/budgets/{$this->trip->id}/board")
            ->assertOk()
            ->assertJsonPath('trip.title', 'Bali with friends')
            ->assertJsonPath('my_role', 'viewer')
            ->assertJsonPath('can_edit', false)
            ->assertJsonPath('members_count', 2)
            ->assertJsonPath('items.0.id', $item->id)
            ->assertJsonPath('items.0.added_by.name', 'Afnan');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/budgets/{$this->trip->id}/board")->assertForbidden();
        $this->getJson("/api/budgets/{$this->trip->id}/activity")->assertForbidden();
        $this->getJson("/api/budgets/{$this->trip->id}/members")->assertForbidden();
    }

    public function test_editors_comment_and_vote_and_the_board_and_history_show_it(): void
    {
        $item = $this->item();
        $editor = $this->member('editor');
        $editor->update(['name' => 'Sara']);
        Sanctum::actingAs($editor);

        $this->postJson("/api/budgets/{$this->trip->id}/items/{$item->id}/comments", ['body' => 'Pool looks great'])
            ->assertCreated()
            ->assertJsonPath('user.name', 'Sara');
        $this->putJson("/api/budgets/{$this->trip->id}/items/{$item->id}/vote", ['value' => 1])->assertOk()->assertJson(['up' => 1, 'down' => 0, 'mine' => 1]);
        $this->putJson("/api/budgets/{$this->trip->id}/items/{$item->id}/vote", ['value' => -1])->assertOk()->assertJson(['up' => 0, 'down' => 1, 'mine' => -1]);

        $this->getJson("/api/budgets/{$this->trip->id}/board")
            ->assertJsonPath('items.0.votes', ['up' => 0, 'down' => 1, 'mine' => -1])
            ->assertJsonPath('items.0.comments.0.body', 'Pool looks great')
            ->assertJsonPath('items.0.comments.0.can_delete', true);

        $history = collect($this->getJson("/api/budgets/{$this->trip->id}/activity")->assertOk()->json())->pluck('description')->all();
        $this->assertSame([
            'voted against "Ubud Rice Terrace Villa"',
            'voted for "Ubud Rice Terrace Villa"',
            'commented on "Ubud Rice Terrace Villa": Pool looks great',
        ], $history);

        $this->putJson("/api/budgets/{$this->trip->id}/items/{$item->id}/vote", ['value' => 0])->assertOk()->assertJson(['up' => 0, 'down' => 0, 'mine' => 0]);
        $this->assertDatabaseCount('votes', 0);
    }

    public function test_viewers_cannot_comment_vote_or_add_items(): void
    {
        $item = $this->item();
        Sanctum::actingAs($this->member('viewer'));

        $this->postJson("/api/budgets/{$this->trip->id}/items/{$item->id}/comments", ['body' => 'Hi'])->assertForbidden();
        $this->putJson("/api/budgets/{$this->trip->id}/items/{$item->id}/vote", ['value' => 1])->assertForbidden();
        $this->postJson('/api/bookings', [
            'type' => 'activity', 'budget_id' => $this->trip->id, 'title' => 'Snorkelling', 'start_at' => '2026-12-02 09:00', 'status' => 'planned',
        ])->assertForbidden();
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_comment_and_vote_validation_and_items_of_other_trips(): void
    {
        $item = $this->item();
        Sanctum::actingAs($this->member('editor'));

        $this->postJson("/api/budgets/{$this->trip->id}/items/{$item->id}/comments", ['body' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body' => 'The body field is required.']);
        $this->putJson("/api/budgets/{$this->trip->id}/items/{$item->id}/vote", ['value' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value' => 'The selected value is invalid.']);

        $otherTrip = $this->tripFor(User::factory()->create(), 'Someone else');
        $this->postJson("/api/budgets/{$otherTrip->id}/items/{$item->id}/comments", ['body' => 'Hi'])->assertNotFound();
    }

    public function test_editors_add_activities_and_change_other_members_items(): void
    {
        $item = $this->item();
        $editor = $this->member('editor');
        Sanctum::actingAs($editor);

        $this->postJson('/api/bookings', [
            'type' => 'activity', 'budget_id' => $this->trip->id, 'title' => 'Snorkelling at Amed', 'start_at' => '2026-12-02 09:00', 'status' => 'planned', 'price' => 35,
        ])->assertCreated();

        $this->putJson("/api/bookings/{$item->id}", array_merge($item->only(['type', 'title', 'price']), [
            'budget_id' => $this->trip->id, 'start_at' => '2026-12-01 15:00', 'end_at' => '2026-12-04 11:00', 'status' => 'confirmed',
        ]))->assertOk()->assertJsonPath('status', 'confirmed');

        $history = ActivityLog::where('budget_id', $this->trip->id)->orderBy('id')->pluck('description')->all();
        $this->assertSame(['added the activity "Snorkelling at Amed"', 'marked "Ubud Rice Terrace Villa" as confirmed'], $history);
        $this->assertSame($this->owner->id, $item->fresh()->user_id);
    }

    public function test_activities_cannot_carry_a_flight_or_a_stay(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/bookings', [
            'type' => 'activity', 'flight_id' => 1, 'budget_id' => $this->trip->id, 'title' => 'Tour', 'start_at' => '2026-12-02 09:00', 'status' => 'planned',
        ])->assertUnprocessable()->assertJsonValidationErrors(['flight_id' => 'The flight id field is prohibited unless type is in flight.']);
    }

    public function test_owner_changes_roles_and_removes_people_and_members_can_leave(): void
    {
        $editorRow = TripMember::factory()->for($this->trip)->role('editor')->create();
        $viewerRow = TripMember::factory()->for($this->trip)->role('viewer')->create();
        $invite = TripMember::factory()->for($this->trip)->invited('pending@example.com')->create();
        Sanctum::actingAs($this->owner);

        $this->patchJson("/api/budgets/{$this->trip->id}/members/{$editorRow->id}", ['role' => 'viewer'])->assertOk();
        $this->assertSame('viewer', $editorRow->fresh()->role);
        $this->deleteJson("/api/budgets/{$this->trip->id}/members/{$invite->id}")->assertOk();

        Sanctum::actingAs($viewerRow->user);
        $this->deleteJson("/api/budgets/{$this->trip->id}/members/{$editorRow->id}")->assertForbidden();
        $this->deleteJson("/api/budgets/{$this->trip->id}/members/{$viewerRow->id}")->assertOk()->assertJsonPath('message', 'You left the trip.');

        $this->assertSame([$editorRow->id], TripMember::pluck('id')->all());
        $descriptions = ActivityLog::orderBy('id')->pluck('description')->all();
        $this->assertSame("changed {$editorRow->user->name}'s role to viewer", $descriptions[0]);
        $this->assertSame(['cancelled the invite for pending@example.com', 'left the trip'], array_slice($descriptions, 1));
    }

    public function test_members_of_another_trip_return_not_found(): void
    {
        $otherTrip = $this->tripFor(User::factory()->create(), 'Someone else');
        $stranger = TripMember::factory()->for($otherTrip)->create();
        Sanctum::actingAs($this->owner);

        $this->patchJson("/api/budgets/{$this->trip->id}/members/{$stranger->id}", ['role' => 'viewer'])->assertNotFound();
        $this->deleteJson("/api/budgets/{$this->trip->id}/members/{$stranger->id}")->assertNotFound();
    }

    public function test_only_owners_see_pending_invites_and_their_links(): void
    {
        TripMember::factory()->for($this->trip)->invited('pending@example.com')->create();
        $editor = $this->member('editor');

        Sanctum::actingAs($editor);
        $this->getJson("/api/budgets/{$this->trip->id}/members")->assertOk()->assertJsonPath('my_role', 'editor')->assertJsonCount(0, 'invites')->assertJsonCount(1, 'members');

        Sanctum::actingAs($this->owner);
        $this->getJson("/api/budgets/{$this->trip->id}/members")->assertJsonCount(1, 'invites')->assertJsonPath('owner.name', 'Afnan');
    }

    public function test_comments_are_deleted_by_their_author_or_an_owner(): void
    {
        $item = $this->item();
        $author = $this->member('editor');
        $otherEditor = $this->member('editor');
        Sanctum::actingAs($author);
        $first = $this->postJson("/api/budgets/{$this->trip->id}/items/{$item->id}/comments", ['body' => 'One'])->json('id');
        $second = $this->postJson("/api/budgets/{$this->trip->id}/items/{$item->id}/comments", ['body' => 'Two'])->json('id');

        Sanctum::actingAs($otherEditor);
        $this->deleteJson("/api/comments/{$first}")->assertForbidden();

        Sanctum::actingAs($author);
        $this->deleteJson("/api/comments/{$first}")->assertOk();
        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/comments/{$second}")->assertOk();
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_trips_lists_owned_and_shared_trips_with_roles(): void
    {
        $sara = User::factory()->create();
        TripMember::factory()->for($this->trip)->for($sara)->role('viewer')->create();
        $sarasTrip = $this->tripFor($sara, 'Sara solo');

        Sanctum::actingAs($sara);
        $trips = collect($this->getJson('/api/trips')->assertOk()->json())->pluck('role', 'title')->all();
        $this->assertSame(['Sara solo' => 'owner', 'Bali with friends' => 'viewer'], $trips);

        $this->getJson('/api/trips?can_edit=1')->assertJsonCount(1)->assertJsonPath('0.id', $sarasTrip->id);
    }

    public function test_changes_are_broadcast_on_the_trips_private_channel(): void
    {
        Event::fake([TripBoardUpdated::class]);
        $item = $this->item();
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/budgets/{$this->trip->id}/items/{$item->id}/comments", ['body' => 'Booked!'])->assertCreated();

        Event::assertDispatched(TripBoardUpdated::class, fn (TripBoardUpdated $event): bool => $event->broadcastOn()[0]->name === "private-trips.{$this->trip->id}"
            && $event->action === 'comment.added'
            && $event->byName === 'Afnan');
    }

    public function test_the_invite_email_escapes_the_trip_title(): void
    {
        $this->trip->update(['title' => '<script>alert(1)</script> trip']);
        $invite = TripMember::factory()->for($this->trip)->invited('sara@example.com')->create();
        $invite->inviter()->associate($this->owner)->save();

        $html = (new TripInviteMail($invite->load('budget.destination', 'inviter'), 'http://127.0.0.1:5173/join/x'))->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
