<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\AuthenticityScorer;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    public function store(
        Request $request,
        EmbeddingService $embeddings,
        AuthenticityScorer $authenticityScorer,
    ): JsonResponse {
        $validated = $request->validate([
            'text' => ['required', 'string'],
            'image_url' => ['nullable', 'url'],
        ]);

        $text = $validated['text'];
        $imageUrl = $validated['image_url'] ?? null;
        $embedding = $embeddings->embed($text);
        $literal = $embeddings->toPgVectorLiteral($embedding);
        $authenticityScore = $authenticityScorer->score($text, $imageUrl);

        // Eloquent cannot natively bind pgvector; insert via CAST(? AS vector).
        $row = DB::selectOne(
            '
                INSERT INTO posts (user_id, text, image_url, embedding, authenticity_score, created_at, updated_at)
                VALUES (?, ?, ?, CAST(? AS vector), ?, NOW(), NOW())
                RETURNING id, user_id, text, image_url, authenticity_score, created_at, updated_at
            ',
            [
                $request->user()->id,
                $text,
                $imageUrl,
                $literal,
                $authenticityScore,
            ]
        );

        $post = Post::query()->with('user:id,name')->findOrFail($row->id);

        return response()->json([
            'id' => $post->id,
            'user_id' => $post->user_id,
            'author_name' => $post->user->name,
            'text' => $post->text,
            'image_url' => $post->image_url,
            'authenticity_score' => (float) $post->authenticity_score,
            'created_at' => $post->created_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
        ], 201);
    }
}
