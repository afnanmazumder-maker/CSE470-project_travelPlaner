# R16 — Group Trip Collaboration

**Owner:** Afnan Mazumder (24141229) · **Branch:** `feature/afnan-r16-group-trip` · **Builds on:** R7 budgets (the trip), R10 bookings (the itinerary items)

> SRS R16: Invite members by email or link as owner, editor, or viewer. The shared itinerary updates in real
> time (Laravel Reverb), members vote and comment on items, and an activity history shows who changed what.

## What it does

- **A trip is an R7 budget.** Its creator is always the owner. Open it from **Group trips** in the header.
- **Invite by email or link.** An owner picks a role (owner, editor or viewer) and either types an email (Voyenta
  emails a join link) or leaves it empty to get a one-time link to send any way they like. Invites last 14 days.
  An email invite only works for that email address. Inviting the same email again replaces the old invite.
- **Roles.** Owners invite people, change roles and remove people. Editors add and change itinerary items, vote and
  comment. Viewers see everything but change nothing. Anyone can leave a trip.
- **Shared itinerary.** The items are the bookings saved to the trip: flights and stays from the Flights and Stays
  pages, and activities added straight on the board. Editors can save to a shared trip, change an item's status
  (planned, confirmed, cancelled) or remove it.
- **Votes and comments** on every item (👍 / 👎, one vote per person; comments can be deleted by their author or an owner).
- **Activity history**: every invite, join, role change, item change, vote and comment, with who did it and when.
- **Real time with Laravel Reverb.** Every change is broadcast on the private channel `trips.{id}`; everyone with the
  board open sees it at once ("Live"). Without Reverb the board still works and refreshes every 15 seconds.

## MVC files in this package

| Layer | Files |
|---|---|
| Model | `backend/app/Models/TripMember.php`, `Comment.php`, `Vote.php`, `ActivityLog.php` (+ factories) |
| | `backend/database/migrations/2026_11_12_183128_create_trip_members_table.php`, `…183129_create_comments_table.php`, `…183130_create_votes_table.php`, `…183131_create_activity_logs_table.php` |
| | `backend/app/Services/TripMemberService.php` (roles, invites, joining), `ItineraryBoardService.php`, `TripActivityService.php` |
| | `backend/app/Events/TripBoardUpdated.php` (broadcast), `backend/app/Observers/BookingObserver.php` (item history) |
| | `backend/app/Mail/TripInviteMail.php`, `backend/resources/views/emails/trip-invite.blade.php` |
| Controller | `backend/app/Http/Controllers/Api/TripMemberController.php`, `CommentController.php`, `ItineraryBoardController.php` |
| | `backend/app/Http/Requests/InviteTripMemberRequest.php`, `UpdateTripMemberRequest.php`, `StoreCommentRequest.php`, `VoteRequest.php` |
| View | `frontend/src/pages/ItineraryBoard.jsx` (SRS: ItineraryBoard) at `/trips/:id` |
| | `frontend/src/components/CollaboratorsPanel.jsx` (SRS: CollaboratorsPanel), `frontend/src/pages/GroupTrips.jsx` at `/trips`, `frontend/src/pages/JoinTrip.jsx` at `/join/:token` |
| | `frontend/src/echo.js` (Laravel Echo client for Reverb) |
| Tests | `backend/tests/Feature/TripCollaborationTest.php` (28 tests) |

Shared files touched (small edits):
`backend/routes/api.php` (one R16 block at the end), `backend/routes/channels.php` (the `trips.{budget}` channel),
`backend/bootstrap/app.php` (broadcast auth at `/api/broadcasting/auth` with the API token),
`backend/config/voyenta.php` (`frontend_url` for invite links), `backend/.env.example` (FRONTEND_URL and Reverb keys),
`backend/composer.json` (`laravel/reverb`), `frontend/package.json` (`laravel-echo`, `pusher-js`),
`frontend/src/App.jsx` (three routes), `frontend/src/components/SiteHeader.jsx` ("Group trips" link),
`frontend/src/pages/Login.jsx` (returns to the invite page after logging in).

Small edits to R10 bookings so they work as itinerary items: a new `activity` booking type, owners and editors of a
group trip may save to it and change its items (`BookingPolicy`, `BookingController`), and the Flights, Stays and
My bookings pages list the trips you can edit (`/api/trips?can_edit=1`) instead of only your own budgets.

## API

| Method | URL | Who | Purpose |
|---|---|---|---|
| GET | `/api/trips` (`?can_edit=1`) | logged in | Trips you own or joined, with your role |
| GET | `/api/budgets/{budget}/members` | members | Owner, members; pending invites with links for owners |
| POST | `/api/budgets/{budget}/invites` | owners | `{email?, role}`: email invite, or a link when there is no email |
| PATCH / DELETE | `/api/budgets/{budget}/members/{member}` | owners (DELETE: also yourself) | Change role / remove, cancel an invite, leave |
| GET | `/api/invites/{token}` | anyone | What the invite is for |
| POST | `/api/invites/{token}/accept` | logged in | Join the trip |
| GET | `/api/budgets/{budget}/board` | members | Trip, your role, items with votes and comments |
| GET | `/api/budgets/{budget}/activity` | members | The newest 50 history lines |
| POST | `/api/budgets/{budget}/items/{booking}/comments` | owners, editors | Comment on an item |
| PUT | `/api/budgets/{budget}/items/{booking}/vote` | owners, editors | `{value: 1, -1 or 0}` |
| DELETE | `/api/comments/{comment}` | author or owner | Delete a comment |

Items are added and changed through R10's `/api/bookings` (with `budget_id`).

## Run it

```bash
cd backend
composer install
php artisan migrate
php artisan reverb:install   # once: writes the REVERB_* keys and sets BROADCAST_CONNECTION=reverb
php artisan reverb:start     # keep running for live updates (port 8080)
php artisan test --compact tests/Feature/TripCollaborationTest.php
```

For the frontend, copy `REVERB_APP_KEY` from `backend/.env` into `frontend/.env.development.local`:

```
VITE_REVERB_APP_KEY=<your key>
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

Then `npm install` and open http://127.0.0.1:5173/trips. Without these steps the board still works and refreshes every 15 seconds.
