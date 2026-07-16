<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingService
{
    /**
     * Call the embedding microservice and return a 384-dim float vector.
     *
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $baseUrl = rtrim(config('services.embedding.url'), '/');

        $response = Http::timeout(10)
            ->acceptJson()
            ->post("{$baseUrl}/embed", ['text' => $text]);

        if (! $response->successful()) {
            throw new RuntimeException('Embedding service request failed: '.$response->body());
        }

        $embedding = $response->json('embedding');

        if (! is_array($embedding) || count($embedding) !== 384) {
            throw new RuntimeException('Embedding service returned an invalid vector.');
        }

        return array_map('floatval', $embedding);
    }

    /**
     * Format a PHP float array as a pgvector literal: [0.1,0.2,...]
     *
     * @param  list<float>  $embedding
     */
    public function toPgVectorLiteral(array $embedding): string
    {
        return '['.implode(',', array_map(
            static fn (float $value): string => sprintf('%.8F', $value),
            $embedding
        )).']';
    }
}
