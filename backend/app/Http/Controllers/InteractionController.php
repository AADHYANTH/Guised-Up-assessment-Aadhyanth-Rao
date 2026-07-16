<?php

namespace App\Http\Controllers;

use App\Models\Interaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InteractionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_id' => ['required', 'integer', 'exists:posts,id'],
            'type' => ['required', 'string', Rule::in(['view', 'reply', 'reaction'])],
        ]);

        $interaction = Interaction::query()->create([
            'user_id' => $request->user()->id,
            'post_id' => $validated['post_id'],
            'type' => $validated['type'],
            'created_at' => now(),
        ]);

        return response()->json($interaction, 201);
    }
}
