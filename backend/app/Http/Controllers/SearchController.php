<?php

namespace App\Http\Controllers;

use App\Services\EmbeddingService;
use App\Services\FeedRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(
        Request $request,
        EmbeddingService $embeddings,
        FeedRankingService $feedRanking,
    ): JsonResponse {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1'],
        ]);

        $queryEmbedding = $embeddings->embed($validated['q']);
        $results = $feedRanking->searchByEmbedding($queryEmbedding, 10);

        return response()->json([
            'query' => $validated['q'],
            'data' => $results,
        ]);
    }
}
