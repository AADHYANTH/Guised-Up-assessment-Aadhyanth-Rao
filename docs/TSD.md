# Guised Up — Technical Solution Document

## Overview

Guised Up is a social feed where ranking blends **authenticity**, **relationship depth**, **semantic similarity**, and **time decay**. The stack:

- **Laravel 11** — REST API, auth, business logic, feed SQL
- **PostgreSQL + pgvector** — relational data + vector search in one database
- **FastAPI** — embedding microservice (mock in dev, swappable to sentence-transformers)
- **Expo React Native** — single feed screen consuming the API

## Architecture

```
Mobile (Expo) ──HTTP──► Laravel API (:8000)
                            │
                            ├── PostgreSQL (users, posts, follows, interactions, vectors)
                            └── HTTP ──► Embedding service (:8001) POST /embed
```

## Vector database choice: pgvector

**Chosen:** PostgreSQL with the `pgvector` extension.

**Why not Pinecone / Weaviate / Qdrant / Chroma?**

| Factor | pgvector |
|--------|----------|
| Ops | One database for posts, users, interactions, and vectors |
| Transactions | Post + embedding insert in same DB connection |
| Joins | Feed ranking SQL can combine relational signals and `<=>` cosine distance |
| Take-home fit | Migrations + raw SQL challenge stay in one dialect |
| Trade-off | IVFFlat index needs tuning at scale; fine for this scope |

Embeddings are stored as `vector(384)` on `posts.embedding`, with an IVFFlat cosine index created when rows exist.

## Embeddings

**Current:** deterministic SHA-256 → seeded NumPy vector (384-d, unit length) in `embedding-service/main.py`.

**Production swap:** uncomment `sentence-transformers` (`all-MiniLM-L6-v2`, 384 dims) or call OpenAI embeddings API. Laravel calls `POST /embed` via `EmbeddingService` and inserts with `CAST(? AS vector)`.

## Authenticity score

Heuristic in `AuthenticityScorer` (0–1):

- Bonus for natural-length text (80–400 chars)
- Penalty for very short text, many hashtags, high emoji density
- Penalty for `image_url` with ≥4 query params (heavy filter/CDN signal)

Stored on `posts.authenticity_score` at creation time.

## Feed ranking

**Endpoint:** `GET /api/feed` — 20 posts per page.

**Candidates:** posts from users the viewer **follows** OR **interacted with** in the last 30 days.

**Score** (weights sum to 1.0):

| Signal | Weight | Source |
|--------|--------|--------|
| Authenticity | 0.25 | `posts.authenticity_score` |
| Relationship depth | 0.30 | Normalized interaction count viewer→author (30d), cap at 20 |
| Semantic similarity | 0.30 | `1 - (post.embedding <=> viewer_interest_vector)` via pgvector |
| Time decay | 0.15 | `exp(-age_hours / 48)` |

**Viewer interest vector:** average embedding of the viewer's last 10 own posts + interacted posts. If none, semantic term is 0.

Implemented in one raw SQL query in `FeedRankingService::paginate()`.

## Search

`GET /api/search?q=` embeds the query via the Python service, then:

```sql
ORDER BY posts.embedding <=> :query_vector LIMIT 10
```

## Auth

Laravel Sanctum bearer tokens. Seeded users: `alice@test.com`, `bob@test.com` (password: `password`).

## Schema (high level)

- `users` — accounts
- `posts` — text, optional image_url, embedding, authenticity_score
- `follows` — follower_id, followee_id (unique pair)
- `interactions` — user_id, post_id, type (`view`|`reply`|`reaction`)

## Tests

| Suite | Coverage |
|-------|----------|
| `FeedTest` | Auth required, pagination |
| `SearchTest` | Semantic ordering with mocked embed |
| `InteractionFeedRankingTest` | Interactions boost relationship depth in feed |
| `PostStoreTest` | Post creation stores embedding |
| `embedding-service` pytest | 384 dims, determinism |

Run: `./scripts/verify-all.sh`

## Mobile

Single `FeedScreen`: auto-login as Alice, infinite scroll feed, debounced search, optimistic reactions, loading/empty/error states. API base URL via `EXPO_PUBLIC_API_URL`.

## SQL analytics

Part D queries in `sql/queries.sql` — D1–D4 with efficiency notes and captured outputs.
