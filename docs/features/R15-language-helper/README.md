# R15 — Language Helper and Cultural Tips

**Owner:** Afnan Mazumder (24141229) · **Branch:** `feature/afnan-r13-language-helper` · **Merge order:** 1 of 5 (no dependencies)

> SRS R15: Phrasebook for the top 10 languages (greetings, food, directions, emergencies) with pronunciation
> and saved favorites, plus etiquette notes on tipping, dress code, customs, and business hours.
> Machine translation is added later.

The first commits and the branch name call this feature R13. It is R15 in the SRS, so it is labelled R15 from here on.

## What it does

- Phrasebook in **10 languages** (Spanish, French, German, Italian, Indonesian, Arabic, Mandarin, Japanese, Thai, Turkish),
  16 phrases each, grouped into greetings, food, directions and emergencies.
- Each phrase shows the script, a romanization where needed, and a pronunciation guide. The 🔊 button reads it
  aloud with the browser's built-in voice (no external service).
- Logged-in travelers save favorites (★); favorites are private to each user.
- Etiquette for **10 countries**, including all six destinations (France, Italy, Japan, Turkey, Thailand, Indonesia).
- Browsing is public, like Explore. Machine translation is shown as planned, not built (as the SRS says).

## MVC files in this package

| Layer | Files |
|---|---|
| Model | `backend/app/Models/Phrase.php`, `backend/app/Models/CulturalTip.php` |
| | `backend/database/migrations/2026_11_04_100000_create_phrases_table.php`, `…100100_create_phrase_favorites_table.php`, `…100200_create_cultural_tips_table.php` |
| | `backend/database/seeders/LanguageSeeder.php` (160 phrases, 40 tips) |
| Controller | `backend/app/Http/Controllers/Api/PhraseController.php` |
| | `backend/app/Http/Requests/PhraseIndexRequest.php`, `backend/app/Http/Requests/CulturalTipRequest.php` |
| View | `frontend/src/pages/LanguageHelper.jsx` (SRS: LanguageHelperPage) at `/phrasebook` |
| Tests | `backend/tests/Feature/PhraseApiTest.php` (8 tests) |

Shared files touched (small, appended edits only):
`backend/routes/api.php` (one R15 block at the end), `frontend/src/App.jsx` (one import, one route),
`frontend/src/components/SiteHeader.jsx` (one "Phrasebook" link).

## API

| Method | URL | Auth | Purpose |
|---|---|---|---|
| GET | `/api/phrases/languages` | – | Languages, categories, `machine_translation: false` |
| GET | `/api/phrases?language=ja&category=food&q=bill` | optional | Phrases grouped by category, with `is_favorite` when logged in |
| GET | `/api/phrases/favorites` | ✓ | The user's saved phrases |
| POST / DELETE | `/api/phrases/{phrase}/favorite` | ✓ | Save / remove a favorite |
| GET | `/api/cultural-tips/countries` | – | Countries with tips |
| GET | `/api/cultural-tips?country=Japan` | – | Tips grouped by topic |

## Run it

```bash
cd backend
php artisan migrate
php artisan db:seed --class=LanguageSeeder
php artisan test --compact tests/Feature/PhraseApiTest.php
```

Then open http://localhost:5173/phrasebook.
