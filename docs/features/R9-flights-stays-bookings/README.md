# R9 — Flight and Stay Search and Booking Management

**Owner:** Afnan Mazumder (24141229) · **Branch:** `feature/afnan-r9-flights-stays` · **Merge order:** 2 of 5 (after R13)

> SRS R9: Searches admin-managed flight data by origin, destination, dates, passengers, and cabin class, sorted
> and filtered by price, duration, stops, and departure time, with multi-city trips, a flexible-date price
> calendar, and price-drop alerts. Filters stays by price, rating, distance, amenities, and property type, with
> map search and price pins. Saves flight and stay bookings with confirmation number, dates, status (planned,
> confirmed, cancelled), and cancellation-deadline reminders. Admins maintain the flight data.

## What it does

- **Flight search** with sort (price, duration, departure, arrival) and filters (max price, stops, duration,
  departure time window). Only fares with enough seats for the party are shown; `total_price = price × passengers`.
- **Multi-city** search (2–6 legs in date order) with the cheapest total.
- **Flexible-date price calendar**: the cheapest fare for every day of a month, banded green / amber / red.
- **Price-drop alerts**: watch a route (optionally a date and a target price). When an admin changes a fare, or the
  hourly `price-alerts:check` runs, a drop fires the `FlightPriceDropped` event. **R16 turns that event into a
  notification**, so this package works on its own and R16 plugs in without editing it.
- **Stay search** on the six destinations with filters (price, rating, km from the centre, property type, all
  selected amenities), sorting, and **map search** with price pins (Leaflet + OpenStreetMap).
- **Bookings** for flights and stays: confirmation number, dates, status, price, cancellation deadline and payment
  date. A booking can belong to a trip, which is an **R7 budget** ("Paris trip"); only the budget owner can add to it.
- **Flight admin page** for users with `role = admin`.

## MVC files in this package

| Layer | Files |
|---|---|
| Model | `backend/app/Models/Flight.php`, `Accommodation.php`, `Booking.php`, `PriceAlert.php` |
| | `backend/database/migrations/2026_11_04_110000_add_role_to_users_table.php` … `110400_create_price_alerts_table.php` (5 files) |
| | `backend/app/Services/FlightSearchService.php`, `StaySearchService.php`, `PriceAlertService.php` |
| | `backend/app/Events/FlightPriceDropped.php`, `backend/config/flights.php` |
| | `backend/database/seeders/FlightSeeder.php` (~37,500 fares, Nov 2026–Jun 2027), `AccommodationSeeder.php` (40 stays) |
| Controller | `backend/app/Http/Controllers/Api/FlightController.php`, `AccommodationController.php`, `BookingController.php` |
| | 7 Form Requests in `backend/app/Http/Requests/` (`SearchFlightsRequest`, `MultiCityFlightsRequest`, `PriceCalendarRequest`, `StorePriceAlertRequest`, `StoreFlightRequest`, `SearchStaysRequest`, `StoreBookingRequest`) |
| | `backend/app/Policies/BookingPolicy.php`, `backend/app/Http/Middleware/EnsureUserIsAdmin.php`, `backend/app/Console/Commands/CheckPriceAlerts.php` |
| View | `frontend/src/pages/FlightSearch.jsx` (FlightSearchPage), `StaySearch.jsx` (StaySearchPage), `Bookings.jsx` (BookingsPage), `FlightAdmin.jsx` (FlightAdminPage) |
| | `frontend/src/components/TravelTabs.jsx`, `frontend/src/travelFormat.js`, `frontend/src/useLeaflet.js` |
| Tests | `backend/tests/Feature/FlightApiTest.php` (10), `StayApiTest.php` (5), `BookingApiTest.php` (6), `backend/tests/Unit/StaySearchServiceTest.php` (2) |

Shared files touched (appended edits only): `backend/routes/api.php` (one R9 block), `backend/routes/console.php`
(one schedule line), `frontend/src/App.jsx` (4 imports, 4 routes), `frontend/src/components/SiteHeader.jsx` ("Travel" link).

Notes for the team:
- The new `users.role` column (default `traveler`) is the admin flag; the R18 admin dashboard can reuse it.
- Leaflet loads from cdnjs only on the stay page, so `package.json` is unchanged. If the team prefers
  `react-leaflet` (as the SRS lists), it can replace `useLeaflet.js` later.
- Flight and booking times are local wall-clock times, serialized as `2026-12-05T10:30` (no time zone).

## API (all need a login)

| Method | URL | Purpose |
|---|---|---|
| GET | `/api/flights/options` | Airports, cabins, departure windows, sorts |
| GET | `/api/flights/search?origin=DAC&destination=BKK&date=2026-12-05&passengers=2&cabin_class=economy` | One-way search (+ `sort`, `max_price`, `max_stops`, `max_duration_hours`, `depart_windows[]`) |
| POST | `/api/flights/multi-city` | `{legs: [{origin, destination, date}], passengers, cabin_class}` |
| GET | `/api/flights/price-calendar?origin=DAC&destination=CDG&cabin_class=economy&month=2027-01` | Cheapest fare per day |
| GET / POST / DELETE | `/api/price-alerts`, `/api/price-alerts/{id}` | Watch a route |
| GET | `/api/stays/options`, `/api/stays/search?destination_id=5&check_in=…&check_out=…`, `/api/stays/{id}` | Stay search (or `north/south/east/west` for map search) |
| REST | `/api/bookings` | List (filter `budget_id`, `type`, `status`), create, show, update, delete |
| GET / POST / PUT / DELETE | `/api/admin/flights` | Admin only |

## Run it

```bash
cd backend
php artisan migrate
php artisan db:seed --class=DestinationSeeder
php artisan db:seed --class=FlightSeeder
php artisan db:seed --class=AccommodationSeeder
php artisan test --compact tests/Feature/FlightApiTest.php tests/Feature/StayApiTest.php tests/Feature/BookingApiTest.php
```

Make a user an admin: `php artisan tinker --execute 'App\Models\User::where("email", "you@example.com")->update(["role" => "admin"]);'`

Pages: `/flights`, `/stays`, `/bookings`, `/admin/flights`.
