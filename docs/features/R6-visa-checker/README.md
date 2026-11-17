# R6 — Visa and Entry Requirement Checker

**Owner:** Ramisa Faria (24101084) · **Branch:** `feature/Ramisa`

> SRS R6: For a passport nationality and destination, shows visa-free, visa on arrival, e-visa, or embassy visa,
> allowed stay, required documents, passport validity rules, and transit warnings, with official links, a
> last-updated date, and a disclaimer. Warns on the trip page when a stop needs a visa.

## What it does

- **Visa checker** at `/visa` (public, like Explore): pick a passport and a destination, optionally a stopover.
  The result shows the requirement type, a plain summary, maximum stay, passport validity, fee, processing time,
  conditions, documents to prepare, transit notes, the official link, when it was last checked, and a disclaimer.
- **Stopover warning**: if the traveler would need a visa to enter the stopover country, the page says so.
- **Saved passport**: logged-in travelers save their nationality once (`users.passport_country`).
- **Trip warning**: the budget page (the team's trip) shows "Visa needed" when the destination needs a visa for
  the traveler's passport, with a link to the details. Without a saved passport it asks the traveler to add one.
- **Admin page** at `/admin/visa` (`role = admin`): filter rules, see counts (official, admin-edited, stale),
  and add, edit or delete rules. Every admin change is marked `source = admin`.
- **Stale flag**: a rule whose last check is older than `visa.stale_after_days` (365) is flagged.

## Where the data comes from (two layers)

| Layer | Source | Fills |
|---|---|---|
| 1. Every country pair (about 39,400 rules) | [Passport Index dataset](https://github.com/imorte/passport-index-data), MIT licence, updated 17 Feb 2026. Shipped in `backend/database/data/visa/` with its licence. | Requirement type and visa-free days |
| 2. Bangladesh passport to the six app destinations | Checked by hand on official or embassy sites on 13 Nov 2026 (`VisaOfficialRuleSeeder`). Fields that could not be confirmed are left empty. | Documents, validity, conditions, fees, processing time, transit notes, official link |

Re-importing the dataset (`php artisan visa:import`) never overwrites `official` or `admin` rows.

**Known gaps:** rules for other purposes than tourism (study, work), dual nationality, residence permits held in
a third country (handled as text in `conditions`), and airport-specific transit rules. Fees and processing times
are only filled where an official page states them.

## Setup

```
cd backend
php artisan migrate
php artisan db:seed --class=VisaSeeder
```

`VisaSeeder` loads 199 countries, the official rules, then the dataset (takes a few seconds).
To refresh the dataset later: `php artisan visa:import --requirements=path/to/passport-index-tidy-iso2.csv`.

## MVC files in this package

| Layer | Files |
|---|---|
| Model | `backend/app/Models/Country.php`, `VisaRequirement.php` |
| | `backend/database/migrations/2026_11_13_100000_create_countries_table.php`, `…100100_create_visa_requirements_table.php`, `…100200_add_passport_country_to_users_table.php` |
| | `backend/app/Services/VisaService.php` (lookup, summaries, transit and trip warnings), `VisaDatasetImporter.php` (CSV import) |
| | `backend/database/seeders/VisaSeeder.php`, `VisaOfficialRuleSeeder.php`, `backend/config/visa.php`, `backend/database/data/visa/*` |
| Controller | `backend/app/Http/Controllers/Api/VisaController.php`, `VisaAdminController.php` |
| | Form Requests: `CheckVisaRequest`, `UpdatePassportRequest`, `VisaRequirementRequest`, `AdminVisaIndexRequest` |
| | `backend/app/Console/Commands/ImportVisaDataset.php` (`visa:import`) |
| View | `frontend/src/pages/VisaChecker.jsx` (SRS: VisaCheckerPage), `frontend/src/pages/VisaAdmin.jsx`, `frontend/src/components/VisaNotice.jsx`, `frontend/src/visaFormat.js` |
| Tests | `backend/tests/Feature/VisaApiTest.php` (15), `backend/tests/Unit/VisaDatasetImporterTest.php` (3) |

Shared files touched (appended edits only): `backend/routes/api.php` (one R6 block), `frontend/src/App.jsx`
(2 imports, 2 routes), `frontend/src/components/SiteHeader.jsx` ("Visa" link),
`frontend/src/pages/BudgetDashboard.jsx` (1 import, `<VisaNotice />` under the links).

Notes for the team:
- The `countries` table (ISO-2 code and English name) can be reused by other features.
- R18 can add visa-deadline reminders by reading `VisaService::forBudget()` for upcoming trips.

## API

| Method | URL | Auth | Purpose |
|---|---|---|---|
| GET | `/api/visa/countries` | – | Countries, default passport, dataset date |
| GET | `/api/visa/check?passport=BD&destination=TH&transit=AE` | – | The result (`transit` optional) |
| GET / PUT | `/api/visa/passport` | ✓ | Read or save `{passport_country: "BD"}` |
| GET | `/api/budgets/{budget}/visa` | ✓ owner | Trip warning for the budget's destination |
| GET | `/api/admin/visa-requirements?passport=BD&destination=&source=official&stale=1` | admin | Paginated rules and counts |
| POST / PUT / DELETE | `/api/admin/visa-requirements`, `/api/admin/visa-requirements/{id}` | admin | Maintain rules |
