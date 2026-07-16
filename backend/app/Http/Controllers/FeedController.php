<?php

namespace App\Http\Controllers;

use App\Services\FeedRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function index(Request $request, FeedRankingService $feedRanking): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));

        return response()->json(
            $feedRanking->paginate($request->user()->id, $page)
        );
    }
}
