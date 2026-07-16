<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_semantically_ordered_results(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();

        // Unit vectors: target aligned with dim0, distractor with dim1.
        $targetEmbedding = $this->unitVector(0);
        $distractorEmbedding = $this->unitVector(1);
        $queryEmbedding = $this->unitVector(0); // identical to target → distance 0

        $matchingId = $this->insertPost(
            $author->id,
            'Unique quantum bananas research notes',
            0.8,
            $targetEmbedding
        );
        $this->insertPost(
            $author->id,
            'Totally unrelated fishing boat chronicle',
            0.8,
            $distractorEmbedding
        );

        Http::fake([
            '*/embed' => Http::response(['embedding' => $queryEmbedding], 200),
        ]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/search?q='.urlencode('quantum bananas'));

        $response->assertOk()
            ->assertJsonPath('query', 'quantum bananas');

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame($matchingId, $data[0]['id']);
        $this->assertSame('Unique quantum bananas research notes', $data[0]['text']);
    }

    /**
     * @return list<float>
     */
    private function unitVector(int $hotIndex): array
    {
        $vector = array_fill(0, 384, 0.0);
        $vector[$hotIndex] = 1.0;

        return $vector;
    }

    /**
     * @param  list<float>  $embedding
     */
    private function insertPost(int $userId, string $text, float $authenticity, array $embedding): int
    {
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
