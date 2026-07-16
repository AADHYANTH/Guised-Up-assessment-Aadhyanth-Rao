<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_requires_authentication(): void
    {
        $this->getJson('/api/feed')->assertUnauthorized();
    }

    public function test_feed_returns_paginated_results(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();

        Follow::query()->create([
            'follower_id' => $viewer->id,
            'followee_id' => $author->id,
            'created_at' => now(),
        ]);

        foreach (range(1, 25) as $i) {
            $this->insertPost($author->id, "Feed post number {$i}", 0.7);
        }

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/feed?page=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('total', 25)
            ->assertJsonCount(20, 'data');

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'author_name',
                    'text',
                    'image_url',
                    'score',
                    'created_at',
                    'time_ago',
                ],
            ],
            'current_page',
            'per_page',
            'total',
            'last_page',
        ]);

        $pageTwo = $this->getJson('/api/feed?page=2');
        $pageTwo->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonCount(5, 'data');
    }

    /**
     * @param  list<float>|null  $embedding
     */
    private function insertPost(int $userId, string $text, float $authenticity, ?array $embedding = null): int
    {
        if ($embedding === null) {
            $row = DB::selectOne(
                '
                    INSERT INTO posts (user_id, text, image_url, authenticity_score, created_at, updated_at)
                    VALUES (?, ?, NULL, ?, NOW(), NOW())
                    RETURNING id
                ',
                [$userId, $text, $authenticity]
            );

            return (int) $row->id;
        }

        $literal = '['.implode(',', array_map(fn ($v) => sprintf('%.8F', $v), $embedding)).']';

        $row = DB::selectOne(
            '
                INSERT INTO posts (user_id, text, image_url, embedding, authenticity_score, created_at, updated_at)
                VALUES (?, ?, NULL, CAST(? AS vector), ?, NOW(), NOW())
                RETURNING id
            ',
            [$userId, $text, $literal, $authenticity]
        );

        return (int) $row->id;
    }
}
