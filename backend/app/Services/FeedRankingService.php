<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FeedRankingService
{
    /** Composite score weights — must sum to 1.0 */
    public const AUTHENTICITY_WEIGHT = 0.25;

    public const RELATIONSHIP_DEPTH_WEIGHT = 0.30;

    public const SEMANTIC_SIMILARITY_WEIGHT = 0.30;

    public const TIME_DECAY_WEIGHT = 0.15;

    public const PER_PAGE = 20;

    /**
     * Rank candidate posts for the viewer using a single SQL query.
     *
     * @return array{data: list<array<string, mixed>>, current_page: int, per_page: int, total: int, last_page: int}
     */
    public function paginate(int $viewerId, int $page = 1): array
    {
        $page = max(1, $page);
        $perPage = self::PER_PAGE;
        $offset = ($page - 1) * $perPage;

        $wAuth = self::AUTHENTICITY_WEIGHT;
        $wRel = self::RELATIONSHIP_DEPTH_WEIGHT;
        $wSem = self::SEMANTIC_SIMILARITY_WEIGHT;
        $wTime = self::TIME_DECAY_WEIGHT;

        /*
         | Ranking SQL overview
         | --------------------
         | 1. candidate_authors — users the viewer follows OR interacted with (30d)
         | 2. viewer_interest   — AVG(embedding) of the viewer's last 10 own/interacted posts
         | 3. relationship      — interaction counts viewer→author over 30d (depth signal)
         | 4. score             — weighted sum of authenticity, depth, cosine sim, time decay
         |
         | Cosine similarity via pgvector: 1 - (embedding <=> interest_vector)
         | Time decay: exp(-age_hours / 48)
         */
        $sql = <<<SQL
            WITH candidate_authors AS (
                SELECT followee_id AS author_id
                FROM follows
                WHERE follower_id = ?
                UNION
                SELECT DISTINCT p.user_id AS author_id
                FROM interactions i
                INNER JOIN posts p ON p.id = i.post_id
                WHERE i.user_id = ?
                  AND i.created_at >= NOW() - INTERVAL '30 days'
            ),
            viewer_history AS (
                SELECT embedding
                FROM (
                    SELECT embedding, created_at
                    FROM posts
                    WHERE user_id = ?
                      AND embedding IS NOT NULL
                    UNION ALL
                    SELECT p.embedding, i.created_at
                    FROM interactions i
                    INNER JOIN posts p ON p.id = i.post_id
                    WHERE i.user_id = ?
                      AND p.embedding IS NOT NULL
                ) history
                ORDER BY created_at DESC
                LIMIT 10
            ),
            viewer_interest AS (
                SELECT AVG(embedding) AS vec
                FROM viewer_history
            ),
            relationship AS (
                SELECT p.user_id AS author_id, COUNT(*)::float AS interaction_count
                FROM interactions i
                INNER JOIN posts p ON p.id = i.post_id
                WHERE i.user_id = ?
                  AND i.created_at >= NOW() - INTERVAL '30 days'
                GROUP BY p.user_id
            ),
            scored AS (
                SELECT
                    posts.id,
                    posts.text,
                    posts.image_url,
                    posts.created_at,
                    posts.authenticity_score,
                    users.name AS author_name,
                    users.id AS author_id,
                    (
                        {$wAuth} * posts.authenticity_score
                        + {$wRel} * LEAST(1.0, COALESCE(relationship.interaction_count, 0) / 20.0)
                        + {$wSem} * CASE
                            WHEN viewer_interest.vec IS NULL OR posts.embedding IS NULL THEN 0
                            ELSE GREATEST(0::float, (1 - (posts.embedding <=> viewer_interest.vec))::float)
                          END
                        + {$wTime} * EXP(
                            -EXTRACT(EPOCH FROM (NOW() - posts.created_at)) / 3600.0 / 48.0
                          )
                    ) AS score
                FROM posts
                INNER JOIN users ON users.id = posts.user_id
                INNER JOIN candidate_authors ON candidate_authors.author_id = posts.user_id
                CROSS JOIN viewer_interest
                LEFT JOIN relationship ON relationship.author_id = posts.user_id
            )
        SQL;

        $viewerBindings = [$viewerId, $viewerId, $viewerId, $viewerId, $viewerId];

        $total = (int) DB::selectOne(
            $sql.' SELECT COUNT(*)::int AS aggregate FROM scored',
            $viewerBindings
        )->aggregate;

        $rows = DB::select(
            $sql.' SELECT * FROM scored ORDER BY score DESC, created_at DESC LIMIT ? OFFSET ?',
            array_merge($viewerBindings, [$perPage, $offset])
        );

        $data = array_map(fn ($row) => $this->formatPostRow($row), $rows);

        return [
            'data' => $data,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Semantic search against post embeddings (cosine distance).
     *
     * @param  list<float>  $queryEmbedding
     * @return list<array<string, mixed>>
     */
    public function searchByEmbedding(array $queryEmbedding, int $limit = 10): array
    {
        $literal = app(EmbeddingService::class)->toPgVectorLiteral($queryEmbedding);

        $rows = DB::select(
            '
                SELECT
                    posts.id,
                    posts.text,
                    posts.image_url,
                    posts.created_at,
                    posts.authenticity_score,
                    users.name AS author_name,
                    users.id AS author_id,
                    GREATEST(0::float, (1 - (posts.embedding <=> CAST(? AS vector)))::float) AS score
                FROM posts
                INNER JOIN users ON users.id = posts.user_id
                WHERE posts.embedding IS NOT NULL
                ORDER BY posts.embedding <=> CAST(? AS vector)
                LIMIT ?
            ',
            [$literal, $literal, $limit]
        );

        return array_map(fn ($row) => $this->formatPostRow($row), $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPostRow(object $row): array
    {
        $createdAt = Carbon::parse($row->created_at);

        return [
            'id' => (int) $row->id,
            'author_id' => (int) $row->author_id,
            'author_name' => $row->author_name,
            'text' => $row->text,
            'image_url' => $row->image_url,
            'authenticity_score' => (float) $row->authenticity_score,
            'score' => round((float) $row->score, 6),
            'created_at' => $createdAt->toIso8601String(),
            'time_ago' => $createdAt->diffForHumans(),
        ];
    }
}
