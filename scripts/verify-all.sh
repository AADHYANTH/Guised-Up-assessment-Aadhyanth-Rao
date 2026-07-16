#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export PATH="/opt/homebrew/opt/postgresql@17/bin:/opt/homebrew/bin:$PATH"

echo "== Guised Up verification =="

echo "-- Backend tests --"
cd "$ROOT/backend"
php artisan test

echo "-- Backfill embeddings (requires :8001) --"
php artisan embeddings:backfill

echo "-- Embedding service tests --"
cd "$ROOT/embedding-service"
source .venv/bin/activate
pytest -q

echo "-- Mobile TypeScript --"
cd "$ROOT/mobile"
npx tsc --noEmit

echo "-- Service health --"
curl -sf http://127.0.0.1:8001/health >/dev/null && echo "embedding: ok" || { echo "embedding: DOWN (start uvicorn on :8001)"; exit 1; }
curl -sf http://127.0.0.1:8000/up >/dev/null && echo "laravel: ok" || { echo "laravel: DOWN (start php artisan serve on :8000)"; exit 1; }

echo "-- API smoke --"
TOKEN=$(curl -sf -X POST http://127.0.0.1:8000/api/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"alice@test.com","password":"password"}' | python3 -c 'import sys,json; print(json.load(sys.stdin)["token"])')

curl -sf http://127.0.0.1:8000/api/feed?page=1 \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  | python3 -c 'import sys,json; d=json.load(sys.stdin); assert d["per_page"]==20 and len(d["data"])>0; print("feed:", len(d["data"]), "items")'

curl -sf --get http://127.0.0.1:8000/api/search \
  --data-urlencode "q=coffee" \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  | python3 -c 'import sys,json; d=json.load(sys.stdin); print("search:", len(d["data"]), "results")'

CODE=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/api/feed -H 'Accept: application/json')
test "$CODE" = "401" && echo "auth guard: ok"

echo "-- SQL D1 row count --"
PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d guisedup -tAc \
  "SELECT COUNT(*) FROM (SELECT u.id FROM interactions i JOIN users u ON u.id=i.user_id WHERE i.created_at >= NOW()-INTERVAL '7 days' GROUP BY u.id ORDER BY COUNT(*) DESC LIMIT 10) t;" \
  | xargs -I{} echo "sql d1 top users: {}"

echo "== All checks passed =="
