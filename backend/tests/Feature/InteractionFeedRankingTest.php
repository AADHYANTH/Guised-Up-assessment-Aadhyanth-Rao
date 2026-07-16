<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InteractionFeedRankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_interactions_raise_relationship_depth_in_feed_ranking(): void
    {
        $viewer = User::factory()->create();
        $favored = User::factory()->create(['name' => 'Favored Author']);
        $control = User::factory()->create(['name' => 'Control Author']);

        foreach ([$favored, $control] as $author) {
            Follow::query()->create([
                'follower_id' => $viewer->id,
                'followee_id' => $author->id,
                'created_at' => now()->subDays(5),
            ]);
        }

        // Same authenticity + timestamps so relationship_depth dominates ranking.
        $createdAt = now()->subHours(2);
        $favoredPostId = $this->insertPost($favored->id, 'Favored author post', 0.5, $createdAt);
        $controlPostId = $this->insertPost($control->id, 'Control author post', 0.5, $createdAt);

        Sanctum::actingAs($viewer);

        // Two interactions against the favored author's post.
        $this->postJson('/api/interactions', [
            'post_id' => $favoredPostId,
            'type' => 'view',
        ])->assertCreated();

        $this->postJson('/api/interactions', [
            'post_id' => $favoredPostId,
            'type' => 'reaction',
        ])->assertCreated();

        $this->assertDatabaseCount('interactions', 2);
        $this->assertDatabaseHas('interactions', [
            'user_id' => $viewer->id,
            'post_id' => $favoredPostId,
            'type' => 'reaction',
        ]);

        $feed = $this->getJson('/api/feed')->assertOk()->json('data');

        $this->assertGreaterThanOrEqual(2, count($feed));

        $byId = collect($feed)->keyBy('id');
        $this->assertTrue($byId->has($favoredPostId));
        $this->assertTrue($byId->has($controlPostId));
        $this->assertGreaterThan(
            $byId[$controlPostId]['score'],
            $byId[$favoredPostId]['score']
        );
        $this->assertSame($favoredPostId, $feed[0]['id']);
    }

    private function insertPost(int $userId, string $text, float $authenticity, $createdAt): int
    {
        $row = DB::selectOne(
            '
                INSERT INTO posts (user_id, text, image_url, authenticity_score, created_at, updated_at)
                VALUES (?, ?, NULL, ?, ?, ?)
                RETURNING id
            ',
            [$userId, $text, $authenticity, $createdAt, $createdAt]
        );

        return (int) $row->id;
    }
}
