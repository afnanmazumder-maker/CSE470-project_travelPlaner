# R16 — Notifications, Reminders and Trip Timeline

**Owner:** Afnan Mazumder (24141229) · **Branch:** `feature/afnan-r16-reminders` · **Merge order:** 3 of 5 (after R13 and R9)

> SRS R16: Scheduled email and in-app alerts for check-in opening, flights within 24 hours, visa deadlines,
> payments, and document expiry; a daily digest on travel days; one timeline of flights, stays, and activities;
> and notification preferences.

## What it does

- **Reminder scan** (`reminders:dispatch`, every 15 minutes) over the traveler's R9 bookings:
  online check-in opening (48 h before by default), flights within 24 hours, payments due, free-cancellation
  deadlines, and a **daily digest** on travel days (a flight that day or a night in a booked stay).
- Every reminder has a **dedupe key**, so it is sent once however often the scan runs.
- Each reminder is stored **in-app** and **emailed** (the log mailer in development; set real SMTP or Resend in `.env`).
- **Notification settings**: in-app and email on/off per reminder type, digest on/off and time, and lead times.
- **Price-drop alerts from R9** arrive here through a listener (`SendPriceDropNotification` handles R9's
  `FlightPriceDropped` event), so R9's code was not changed.
- **Trip timeline**: one list of flights, stay check-in/check-out and booking deadlines, grouped by day,
  for all bookings or for one trip (an R7 budget). "Run reminder scan now" triggers the scan on demand.
- A 🔔 bell in the header shows the unread count.

Not covered yet, because the features they depend on are not on `main`: **visa deadlines** need R6 and
**document expiry** needs R15. The scan is built so each can be added as one more method in `ReminderService`
when those tables exist. Activities join the timeline when R5 stores itinerary days in Laravel.

## MVC files in this package

| Layer | Files |
|---|---|
| Model | `backend/app/Models/Notification.php` (table `travel_notifications`), `NotificationPreference.php` |
| | `backend/database/migrations/2026_11_04_120000_create_travel_notifications_table.php`, `…120100_create_notification_preferences_table.php` |
| | `backend/app/Services/NotificationService.php`, `ReminderService.php`, `TimelineService.php` |
| | `backend/app/Listeners/SendPriceDropNotification.php`, `backend/app/Mail/TravelReminderMail.php`, `backend/resources/views/emails/reminder.blade.php` |
| Controller | `backend/app/Http/Controllers/Api/NotificationController.php`, `backend/app/Http/Requests/UpdateNotificationSettingsRequest.php`, `backend/app/Console/Commands/DispatchReminders.php` |
| View | `frontend/src/pages/Timeline.jsx` (TimelinePage), `NotificationSettings.jsx` (NotificationSettings), `Notifications.jsx` (inbox), `frontend/src/components/NotificationBell.jsx` |
| Tests | `backend/tests/Feature/ReminderApiTest.php` (7), `backend/tests/Feature/NotificationApiTest.php` (7) |

Shared files touched (appended edits only): `backend/routes/api.php` (one R16 block), `backend/routes/console.php`
(one schedule line), `frontend/src/App.jsx` (2 imports, 3 routes), `frontend/src/components/SiteHeader.jsx` (bell).

## API (all need a login)

| Method | URL | Purpose |
|---|---|---|
| GET | `/api/notifications` | In-app notifications and unread count |
| POST | `/api/notifications/{id}/read`, `/api/notifications/read-all` | Mark read |
| GET / PUT | `/api/notification-settings` | Channels per type, digest, lead times |
| GET | `/api/timeline`, `/api/budgets/{budget}/timeline` | Timeline of all bookings / one trip |
| POST | `/api/reminders/run` | Run the reminder scan now for the signed-in user |

## Run it

```bash
cd backend
php artisan migrate
php artisan test --compact tests/Feature/ReminderApiTest.php tests/Feature/NotificationApiTest.php
php artisan schedule:work        # runs reminders:dispatch every 15 minutes
```

Pages: `/timeline`, `/notifications`, `/notification-settings`.
