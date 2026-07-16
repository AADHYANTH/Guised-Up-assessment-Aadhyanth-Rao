# Guised Up — Take-Home Submission

Social platform take-home: Laravel API, Python embeddings, React Native feed, and PostgreSQL analytics.

## Repo layout

| Path | Purpose |
|------|---------|
| `backend/` | Laravel 11 API (Sanctum, pgvector, feed ranking) |
| `embedding-service/` | FastAPI mock embedding microservice |
| `mobile/` | Expo React Native feed screen |
| `sql/` | Part D raw SQL queries |
| `docs/TSD.md`, `docs/TSD.pdf` | Technical solution document (Part B) |
| `scripts/verify-all.sh` | One-command verification |

## Prerequisites

- PHP 8.2+ with `pdo_pgsql`
- Composer
- PostgreSQL 17+ with **pgvector**
- Python 3.11+
- Node.js 20+

## Quick start

### 1. Database

```bash
createdb guisedup          # or via psql
createdb guisedup_test
psql -d guisedup -c "CREATE EXTENSION IF NOT EXISTS vector;"
psql -d guisedup_test -c "CREATE EXTENSION IF NOT EXISTS vector;"
```

### 2. Embedding service (start before seed/backfill)

```bash
cd embedding-service
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
uvicorn main:app --host 127.0.0.1 --port 8001
```

### 3. Backend

```bash
cd backend
cp .env.example .env       # set DB_* if needed
composer install
php artisan key:generate
php artisan migrate:fresh --seed    # backfills embeddings when :8001 is up
php artisan embeddings:backfill     # run if seed happened before embedder was up
php artisan serve --host=127.0.0.1 --port=8000
```

**Test users:** `alice@test.com` / `password`, `bob@test.com` / `password`

### 4. Mobile

```bash
cd mobile
cp .env.example .env       # EXPO_PUBLIC_API_URL=http://127.0.0.1:8000/api
npm install
npx expo start
```

Android emulator: use `http://10.0.2.2:8000/api` in `.env`.

## API endpoints

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/register` | — |
| POST | `/api/login` | — |
| POST | `/api/posts` | Sanctum |
| GET | `/api/feed?page=` | Sanctum |
| GET | `/api/search?q=` | Sanctum |
| POST | `/api/interactions` | Sanctum |

## Verify everything

With Laravel (`:8000`) and embedding service (`:8001`) running:

```bash
chmod +x scripts/verify-all.sh
./scripts/verify-all.sh
```

## Tests

```bash
cd backend && php artisan test
cd embedding-service && source .venv/bin/activate && pytest -q
cd mobile && npx tsc --noEmit
```

## SQL challenge

```bash
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d guisedup -f sql/queries.sql
```

See `sql/queries.sql` for D1–D4 with captured outputs.
