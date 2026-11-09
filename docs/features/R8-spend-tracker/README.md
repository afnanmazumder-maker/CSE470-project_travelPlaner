# R8 — Spend Plan vs Actual Cost Tracker and Deviation Report

**Owner:** Afnan Mazumder (24141229) · **Branch:** `feature/afnan-r8-spend-tracker` · **Merge order:** 4 of 5 (after R13, R9, R16)

> SRS R8: Expenses are logged in local currency and converted to home currency using daily Frankfurter rates
> (cached and refreshed on a schedule), storing the rate used. Each expense is matched to its planned category and
> day, and the deviation is computed per category, per day, and for the whole trip, as an amount and a percentage.
> Spending outside any planned category is flagged as Miscellaneous/Unplanned, and alerts fire when a category
> passes a set threshold (for example 10% over plan). An end-of-trip variance report with charts exports as PDF or CSV.

## How it fits with R7 (Aritra)

R7 already plans a budget per category and records USD expenses. R8 **extends** that instead of duplicating it:

- Expenses are still added with R7's `POST /api/budgets/{budget}/expenses`. R8 adds an optional `currency`; the
  amount is converted to USD with the rate for the spending date, and `amount` stays in USD, so **R7's summary and
  dashboard keep working unchanged**. The original amount, currency, rate and rate date are stored too.
- R8's own settings (trip start date, alert threshold, which alerts are raised) live in a new `budget_trackings`
  table, so R7's `budgets` table and model are untouched.
- Category `other` = **Miscellaneous / Unplanned**.

Small edits to R7 files (please review, Aritra):

| File | Change |
|---|---|
| `app/Models/Expense.php` | 5 new fillable fields and casts (currency, original amount, rate) |
| `app/Http/Requests/StoreExpenseRequest.php` | accepts category `other` and an optional `currency` |
| `app/Http/Controllers/Api/ExpenseController.php` | converts the currency, defaults an empty date to today, checks thresholds after add/delete |
| `app/Services/BudgetService.php` | one line, so an `other` expense is counted in the total instead of failing |
| `frontend/src/pages/BudgetDashboard.jsx` | two links to the R8 pages under the title |

**Bug fixed on the way:** on `main`, adding an expense on the budget page with the date box empty returns
**500** (`null value in column "spent_on"`), because the page sends `spent_on: null`. The new `store()` treats an
empty date as today. A regression test covers it.

## What it does

- Log expenses in any of 30 ECB currencies (BDT and AED are not published by the ECB, so they are rejected).
- **Frankfurter rates** are fetched once per spending date, cached in `exchange_rates`, and refreshed daily by
  `rates:refresh`. If Frankfurter is down, the newest stored rate is used and the expense records `rate_source = fallback`.
- **Deviation** per category, per trip day (after setting the start date) and for the whole trip, as an amount and a %.
  Flights, visa and insurance are planned on day 0 (pre-trip); the rest is spread evenly over the trip days.
- **Threshold alerts** (default 10%) fire **once per crossing** as an R16 `budget_alert` notification and re-arm when
  spending falls back under.
- **Variance report** with insights, category bars, a cumulative chart, currencies used and top expenses.
  **CSV** downloads from the API. **PDF** uses the browser's "Save as PDF" on a print-ready page, so no new
  Composer package was added. If the team approves `barryvdh/laravel-dompdf` (listed in the SRS), a server-side PDF can be added.

## MVC files in this package

| Layer | Files |
|---|---|
| Model | `backend/app/Models/ExchangeRate.php`, `BudgetTracking.php` (+ edits to `Expense.php`) |
| | `backend/database/migrations/2026_11_04_130000_create_exchange_rates_table.php`, `…130100_add_currency_columns_to_expenses_table.php`, `…130200_create_budget_trackings_table.php` |
| | `backend/app/Services/CurrencyService.php`, `DeviationService.php`, `backend/app/Exceptions/CurrencyUnavailableException.php`, `backend/config/currency.php` |
| Controller | `backend/app/Http/Controllers/Api/SpendTrackerController.php`, `backend/app/Http/Requests/UpdateBudgetTrackingRequest.php` (+ edits to `ExpenseController.php`, `StoreExpenseRequest.php`) |
| | `backend/app/Console/Commands/RefreshExchangeRates.php` |
| View | `frontend/src/pages/SpendTracker.jsx` (ExpensePage + BudgetDashboard planned vs actual), `frontend/src/pages/VarianceReport.jsx` (VarianceReport) |
| Tests | `backend/tests/Feature/SpendTrackerApiTest.php` (10), `backend/tests/Unit/DeviationServiceTest.php` (8), `backend/tests/Unit/CurrencyServiceTest.php` (4); R7's 22 tests still pass |

Shared files touched (appended edits only): `backend/routes/api.php` (one R8 block), `backend/routes/console.php`
(one schedule line), `frontend/src/App.jsx` (2 imports, 2 routes).

## API (all need a login)

| Method | URL | Purpose |
|---|---|---|
| POST | `/api/budgets/{budget}/expenses` | R7 route; now also accepts `currency` and category `other` |
| GET | `/api/currencies` | Supported currencies and the newest cached rate date |
| GET | `/api/budgets/{budget}/deviation` | Planned vs actual per category, per day and in total |
| PUT | `/api/budgets/{budget}/tracking` | `{start_date, alert_threshold_pct}` |
| GET | `/api/budgets/{budget}/variance-report` | Report with chart data |
| GET | `/api/budgets/{budget}/variance-report.csv` | CSV download |

## Run it

```bash
cd backend
php artisan migrate
php artisan rates:refresh
php artisan test --compact tests/Feature/SpendTrackerApiTest.php tests/Feature/BudgetApiTest.php tests/Unit/DeviationServiceTest.php tests/Unit/CurrencyServiceTest.php
```

Pages: open a budget, then "Track spending in any currency" (`/budgets/{id}/spending`) and "Variance report" (`/budgets/{id}/report`).
