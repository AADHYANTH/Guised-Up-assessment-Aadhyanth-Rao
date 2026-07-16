<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\EmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillEmbeddings extends Command
{
    protected $signature = 'embeddings:backfill';

    protected $description = 'Generate and store embeddings for posts missing vectors';

    public function handle(EmbeddingService $embeddings): int
    {
        $posts = Post::query()->whereNull('embedding')->get();

        if ($posts->isEmpty()) {
            $this->info('No posts need backfill.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($posts->count());
        $bar->start();

        $updated = 0;

        foreach ($posts as $post) {
            try {
                $vector = $embeddings->embed($post->text);
                $literal = $embeddings->toPgVectorLiteral($vector);

                DB::update(
                    'UPDATE posts SET embedding = CAST(? AS vector), updated_at = NOW() WHERE id = ?',
                    [$literal, $post->id]
                );

                $updated++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("Skipped post {$post->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Backfilled {$updated} post(s).");

        return self::SUCCESS;
    }
}
