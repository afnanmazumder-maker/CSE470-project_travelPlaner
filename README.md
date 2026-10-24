# Voyenta: International Tour Planner

CSE470 (Software Engineering) group project, Section 11. Voyenta helps travelers plan trips in one place: destination discovery, personalised recommendations, itineraries, budgets, documents and more. The full requirements are in [`CSE470_SRS_Voyenta.pdf`](CSE470_SRS_Voyenta.pdf).

## How the project is organised

The repository holds three separate programs that talk to each other:

```
.
├── backend/      Laravel 13 REST API (Model + Controller layers), PostgreSQL
├── frontend/     React app built with Vite (View layer)
├── ai-service/   Python FastAPI service for recommendations and itinerary generation
└── CSE470_SRS_Voyenta.pdf
```

How a request flows:

1. The React app (View) sends a request to the Laravel API.
2. A Laravel controller validates it, uses models and services, and returns JSON.
3. For AI features, Laravel calls the FastAPI service, which never touches the database. Laravel sends the data in and stores whatever comes back.

React never talks to the database, or to the AI service, directly.

## Ports used in development

| Part               | URL                   |
| ------------------ | --------------------- |
| Laravel backend    | http://127.0.0.1:8000 |
| FastAPI AI service | http://127.0.0.1:8001 |
| React frontend     | http://localhost:5173 |

FastAPI uses 8001 so it can run at the same time as Laravel.

## What you need installed

| Tool       | Version       | Notes                                                                                                                                                                                                                     |
| ---------- | ------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PHP        | 8.3 or newer  | Use a patched build: 8.3.36, 8.4.27, 8.5.12 or newer (the 21 Oct 2026 security release). On Windows, [Laravel Herd](https://herd.laravel.com/download/windows) installs PHP, Composer and the Laravel installer together. |
| Composer   | current       | Comes with Herd.                                                                                                                                                                                                          |
| Node.js    | 20 or newer   | Includes npm.                                                                                                                                                                                                             |
| PostgreSQL | 18            | Install from postgresql.org. Remember the password you set for the `postgres` user.                                                                                                                                       |
| Python     | 3.11 or newer | For the AI service.                                                                                                                                                                                                       |
| Git        | current       |                                                                                                                                                                                                                           |

## First-time setup

### 1. Database

Create an empty PostgreSQL database named `voyenta` (for example in pgAdmin: right-click Databases, then Create, then Database).

### 2. Backend (Laravel)

```
cd backend
composer install
copy .env.example .env          # Mac/Linux: cp .env.example .env
php artisan key:generate
```

Open `backend/.env` and set your own database details:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=voyenta
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password
```

Then create the tables and start the server:

```
php artisan migrate
php artisan serve
```

Check it works: http://127.0.0.1:8000/api/health should show `{"status":"ok"}`.

`.env` is private to your machine and is never committed. If a teammate adds a new setting, it goes into `.env.example`.

### 3. Frontend (React)

```
cd frontend
npm install
npm run dev
```

Open http://localhost:5173.

### 4. AI service (FastAPI)

```
cd ai-service
python -m venv venv
venv\Scripts\activate           # Mac/Linux: source venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8001
```

Interactive API docs: http://127.0.0.1:8001/docs. See [`ai-service/README.md`](ai-service/README.md) for the endpoints.

## Day-to-day workflow

1. Update `main`, then branch from it:
   ```
   git checkout main
   git pull
   git checkout -b feature/your-name
   ```
2. Work in small steps and commit often with clear messages. The commit history is sprint evidence for the course.
3. Push your branch and open a pull request into `main`. Ask a teammate to review it before merging.
4. Do not push straight to `main`.
5. Before starting each work session, bring your branch up to date:
   ```
   git checkout main
   git pull
   git checkout feature/your-name
   git merge main
   ```

Never commit: `.env` files, `vendor/`, `node_modules/`, or Python `venv/` folders. The `.gitignore` files already cover them, but check `git status` before committing.

Each person keeps to their own features' files where possible. Shared files (routes, the User model, the main layout) change often, so keep edits there small and merge `main` frequently.

## Troubleshooting

- **`php artisan serve` keeps printing "Failed to listen on 127.0.0.1:8000"** (reported with PHP 8.5 on Windows with Herd). Switch Herd to PHP 8.4 and try again.
- **`psql` is not recognised.** It is not required. The Laravel database driver and pgAdmin are enough. To use it, add `C:\Program Files\PostgreSQL\18\bin` to the existing `Path` variable (not as a new variable), then open a new terminal.
- **A terminal does not see a newly installed tool.** Close it and open a new one. Terminals read the PATH only when they start.
- **A port is already in use.** Another copy of the server is probably still running. Stop it with Ctrl+C or close its terminal.
- **Import errors in the AI service.** Make sure the terminal is inside `ai-service` with `(venv)` showing, and that VS Code is using the interpreter in `ai-service/venv`.

## Status

- AI service: recommendations, interest-weight updates and itinerary generation are implemented and tested.
- Backend and frontend: project skeletons are in place. Authentication is being built next.
