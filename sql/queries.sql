-- Guised Up — PostgreSQL analysis queries
-- Schema matches Laravel migrations in ./backend (users, posts, follows, interactions).
-- Run against local DB: PGPASSWORD=postgres psql -h 127.0.0.1 -U postgres -d guisedup -f sql/queries.sql

-- =============================================================================
-- D1: Top 10 most active users in the last 7 days
-- =============================================================================
-- What: Ranks users by total interaction rows (view + reply + reaction) created
--       in the last 7 days.
-- Why efficient: Aggregates only recent interactions then joins users once.
--       Relies on users_pkey for the join; a sequential/filter pass on
--       interactions.created_at is fine at seed scale. For larger tables, an
--       index on interactions(created_at) or (created_at, user_id) would help
--       the time window. Existing interactions_user_id_post_id_index supports
--       related per-user lookups.

SELECT
  u.id AS user_id,
  u.email,
  u.name,
  COUNT(*)::int AS total_interactions
FROM interactions i
INNER JOIN users u ON u.id = i.user_id
WHERE i.created_at >= NOW() - INTERVAL '7 days'
GROUP BY u.id, u.email, u.name
ORDER BY total_interactions DESC, u.id ASC
LIMIT 10;

-- Real output (seeded guisedup, captured 2026-07-15):
--  user_id |            email            |         name          | total_interactions
-- ---------+-----------------------------+-----------------------+--------------------
--        1 | alice@test.com              | Alice Test            |                 92
--        2 | bob@test.com                | Bob Test              |                  9
--        9 | jenkins.hellen@example.net  | Carmel O'Kon PhD      |                  9
--       22 | yortiz@example.org          | Tatyana McGlynn       |                  9
--       17 | west.henri@example.net      | Helmer Carroll        |                  8
--       20 | effertz.cortney@example.com | Hilton Bradtke        |                  8
--        5 | shaina.moen@example.com     | Guiseppe Schmitt      |                  7
--       13 | koepp.ulises@example.com    | Zion Hammes           |                  7
--        6 | raphaelle63@example.com     | Stanford Rowe V       |                  6
--        7 | qrogahn@example.net         | Mr. Arvel Dickens DVM |                  6
-- (10 rows)


-- =============================================================================
-- D2: Posts from authors Alice interacts with most (last 30 days)
-- =============================================================================
-- What: For alice@test.com (id = 1 in this seed), find authors she has
--       interacted with, rank them by interaction frequency, and return those
--       authors' posts from the last 30 days ordered by that frequency.
-- Why efficient: Builds a small author_affinity CTE from Alice's interactions
--       (uses interactions_user_id_post_id_index on user_id), joins posts via
--       posts_user_id_index, and filters with posts_created_at_index for the
--       30-day window. Avoids scanning all posts before narrowing authors.

WITH alice AS (
  SELECT id
  FROM users
  WHERE email = 'alice@test.com' -- seed id = 1
),
author_affinity AS (
  SELECT
    p.user_id AS author_id,
    COUNT(*)::int AS interaction_frequency
  FROM interactions i
  INNER JOIN posts p ON p.id = i.post_id
  CROSS JOIN alice
  WHERE i.user_id = alice.id
  GROUP BY p.user_id
)
SELECT
  p.id AS post_id,
  p.user_id AS author_id,
  u.email AS author_email,
  aa.interaction_frequency,
  LEFT(p.text, 60) AS text_preview,
  p.created_at
FROM posts p
INNER JOIN author_affinity aa ON aa.author_id = p.user_id
INNER JOIN users u ON u.id = p.user_id
WHERE p.created_at >= NOW() - INTERVAL '30 days'
ORDER BY aa.interaction_frequency DESC, p.created_at DESC;

-- Real output (seeded guisedup, alice id=1, captured 2026-07-15):
--  post_id | author_id |        author_email        | interaction_frequency |                         text_preview                         |     created_at
-- ---------+-----------+----------------------------+-----------------------+--------------------------------------------------------------+---------------------
--        1 |         3 | dayton37@example.org       |                    45 | Just tried a new coffee blend — unexpectedly great.          | 2026-07-15 17:08:20
--        2 |         3 | dayton37@example.org       |                    45 | Weekend hike photos coming soon.                             | 2026-07-15 15:08:20
--       36 |         3 | dayton37@example.org       |                    45 | Made cold brew overnight. Patience pays.                     | 2026-07-15 14:08:20
--        3 |         3 | dayton37@example.org       |                    45 | Anyone else stuck debugging Laravel migrations at midnight?  | 2026-07-15 12:08:20
--       40 |         3 | dayton37@example.org       |                    45 | If your API needs a novel of docs, simplify the API.         | 2026-07-15 09:08:20
--        4 |         3 | dayton37@example.org       |                    45 | Hot take: authenticity beats polish every time.              | 2026-07-15 06:08:20
--        5 |         3 | dayton37@example.org       |                    45 | Shipping a small feature today. Feels good.                  | 2026-07-15 00:08:20
--       37 |         3 | dayton37@example.org       |                    45 | Reading fiction again after months of docs only.             | 2026-07-02 18:08:20
--       39 |         3 | dayton37@example.org       |                    45 | Clouds look painted tonight.                                 | 2026-06-24 18:08:20
--       38 |         3 | dayton37@example.org       |                    45 | Small wins compound. Logged three today.                     | 2026-06-22 18:08:20
--       11 |         5 | shaina.moen@example.com    |                    21 | Cooked pasta from scratch. Never going back.                 | 2026-07-09 18:08:20
--       12 |         5 | shaina.moen@example.com    |                    21 | That feeling when tests finally go green.                    | 2026-07-08 18:08:20
--       13 |         5 | shaina.moen@example.com    |                    21 | Random thought: social feeds need more signal, less noise.   | 2026-07-05 18:08:20
--       14 |         5 | shaina.moen@example.com    |                    21 | Travel tip — always pack one nicer outfit than you think.    | 2026-07-03 18:08:20
--       15 |         5 | shaina.moen@example.com    |                    21 | Sketching UI ideas on paper still beats jumping into Figma f | 2026-07-01 18:08:20
--        6 |         4 | roob.micheal@example.net   |                    20 | Looking for book recs that are not productivity porn.        | 2026-07-14 18:08:20
--        7 |         4 | roob.micheal@example.net   |                    20 | City lights hit different after rain.                        | 2026-07-13 18:08:20
--        8 |         4 | roob.micheal@example.net   |                    20 | Built a tiny side project and already overthinking the name. | 2026-07-12 18:08:20
--        9 |         4 | roob.micheal@example.net   |                    20 | Gym streak: day 12. Legs are toast.                          | 2026-07-11 18:08:20
--       10 |         4 | roob.micheal@example.net   |                    20 | Unpopular opinion: meetings should be emails.                | 2026-07-10 18:08:20
--       16 |         2 | bob@test.com               |                     4 | Neighborhood farmers market haul.                            | 2026-07-01 18:08:20
--       17 |         2 | bob@test.com               |                     4 | Learning pgvector. Vectors are just spicy arrays.            | 2026-06-24 18:08:20
--       18 |         2 | bob@test.com               |                     4 | Playlist of the week is all lo-fi and no shame.              | 2026-06-20 18:08:20
--       22 |         7 | qrogahn@example.net        |                     2 | Friend recommended this dumpling place. Instant favorite.    | 2026-07-14 13:08:20
--       31 |         7 | qrogahn@example.net        |                     2 | Street food > fine dining most weeknights.                   | 2026-07-07 18:08:20
--       26 |        11 | hjerde@example.net         |                     1 | Trying to post less and say more.                            | 2026-07-15 16:08:20
--       21 |         6 | raphaelle63@example.com    |                     1 | Prototype day: broken, ugly, and useful.                     | 2026-07-15 10:08:20
--       27 |        12 | qmarks@example.com         |                     1 | Code review tip: ask why before how.                         | 2026-07-12 11:08:20
--       23 |         8 | jamie05@example.net        |                     1 | Writing notes by hand again. Retention is wild.              | 2026-07-08 16:08:20
--       24 |         9 | jenkins.hellen@example.net |                     1 | Quiet Sunday, loud keyboard.                                 | 2026-07-06 18:08:20
--       32 |         9 | jenkins.hellen@example.net |                     1 | Reminder: ship, then iterate.                                | 2026-07-04 18:08:20
-- (31 rows)


-- =============================================================================
-- D3: Posts viewed > 100 times with zero reactions
-- =============================================================================
-- What: Finds posts whose interaction history has more than 100 views and no
--       reactions; returns post_id, author_id, view_count, created_at.
-- Why efficient: Groups interactions by post_id using
--       interactions_post_id_type_index (post_id, type) so view/reaction
--       filters are index-friendly, then joins posts via posts_pkey.

SELECT
  p.id AS post_id,
  p.user_id AS author_id,
  COUNT(*) FILTER (WHERE i.type = 'view')::int AS view_count,
  p.created_at
FROM posts p
INNER JOIN interactions i ON i.post_id = p.id
GROUP BY p.id, p.user_id, p.created_at
HAVING
  COUNT(*) FILTER (WHERE i.type = 'view') > 100
  AND COUNT(*) FILTER (WHERE i.type = 'reaction') = 0
ORDER BY view_count DESC, p.id ASC;

-- NOTE: Seed data max views/post was 8, so this query returns 0 rows naturally.
-- Manual test insert for verification (then deleted):
--   * user sql.fixture@test.local (id 24)
--   * post id 43 with 101 'view' interactions and 0 reactions
-- Real output during that verification run:
--  post_id | author_id | view_count |     created_at
-- ---------+-----------+------------+---------------------
--       43 |        24 |        101 | 2026-07-13 23:51:00
-- (1 row)
-- Fixture user/posts/interactions were deleted afterward; seed DB restored
-- (posts=42, interactions=310, users=23). Re-running now yields 0 rows.


-- =============================================================================
-- D4: Potential spam — users with > 20 posts in the last 24 hours
-- =============================================================================
-- What: Detects accounts that created more than 20 posts in the last day and
--       returns email + post_count.
-- Why efficient: Filters posts by created_at (posts_created_at_index), groups
--       by user_id (posts_user_id_index), then joins users via users_pkey /
--       users_email_unique for the email column.

SELECT
  u.email,
  COUNT(*)::int AS post_count
FROM posts p
INNER JOIN users u ON u.id = p.user_id
WHERE p.created_at >= NOW() - INTERVAL '24 hours'
GROUP BY u.id, u.email
HAVING COUNT(*) > 20
ORDER BY post_count DESC, u.email ASC;

-- NOTE: Seed users do not naturally exceed 20 posts/day, so this returns 0 rows.
-- Manual test insert for verification (then deleted):
--   * same fixture user sql.fixture@test.local
--   * 21 posts created within the last 24 hours
-- Real output during that verification run:
--          email          | post_count
-- ------------------------+------------
--  sql.fixture@test.local |         21
-- (1 row)
-- Fixture data removed afterward; seed DB restored. Re-running now yields 0 rows.
