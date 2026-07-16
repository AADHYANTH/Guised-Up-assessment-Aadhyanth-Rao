<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_post_with_embedding(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $embedding = array_fill(0, 384, 0.0);
        $embedding[0] = 1.0;

        Http::fake([
            '*/embed' => Http::response(['embedding' => $embedding], 200),
        ]);

        $response = $this->postJson('/api/posts', [
            'text' => 'A thoughtful post about authenticity and community on Guised Up with enough length to score well.',
            'image_url' => 'https://example.com/photo.jpg',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('author_name', $user->name);

        $this->assertDatabaseHas('posts', [
            'id' => $response->json('id'),
            'user_id' => $user->id,
        ]);

        $hasEmbedding = DB::table('posts')
            ->where('id', $response->json('id'))
            ->whereNotNull('embedding')
            ->exists();

        $this->assertTrue($hasEmbedding);
    }
}
