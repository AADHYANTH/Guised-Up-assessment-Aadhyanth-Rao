<?php

namespace Database\Seeders;

/*
|--------------------------------------------------------------------------
| Test credentials
|--------------------------------------------------------------------------
| alice@test.com / password
| bob@test.com   / password
*/

use App\Models\Follow;
use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $alice = User::factory()->create([
            'name' => 'Alice Test',
            'email' => 'alice@test.com',
            'password' => Hash::make('password'),
        ]);

        $bob = User::factory()->create([
            'name' => 'Bob Test',
            'email' => 'bob@test.com',
            'password' => Hash::make('password'),
        ]);

        $randomUsers = User::factory(20)->create();
        $allUsers = collect([$alice, $bob])->merge($randomUsers)->values();

        // A few users Alice follows heavily so her feed ranking is predictable.
        $aliceFavorites = $randomUsers->take(3)->values();
        $otherUsers = $randomUsers->slice(3)->values();

        $postBodies = [
            'Just tried a new coffee blend — unexpectedly great.',
            'Weekend hike photos coming soon.',
            'Anyone else stuck debugging Laravel migrations at midnight?',
            'Hot take: authenticity beats polish every time.',
            'Shipping a small feature today. Feels good.',
            'Looking for book recs that are not productivity porn.',
            'City lights hit different after rain.',
            'Built a tiny side project and already overthinking the name.',
            'Gym streak: day 12. Legs are toast.',
            'Unpopular opinion: meetings should be emails.',
            'Cooked pasta from scratch. Never going back.',
            'That feeling when tests finally go green.',
            'Random thought: social feeds need more signal, less noise.',
            'Travel tip — always pack one nicer outfit than you think.',
            'Sketching UI ideas on paper still beats jumping into Figma first.',
            'Neighborhood farmers market haul.',
            'Learning pgvector. Vectors are just spicy arrays.',
            'Playlist of the week is all lo-fi and no shame.',
            'Finished a long-overdue email. Adulting achieved.',
            'Sunrise run. Worth the early alarm for once.',
            'Prototype day: broken, ugly, and useful.',
            'Friend recommended this dumpling place. Instant favorite.',
            'Writing notes by hand again. Retention is wild.',
            'Quiet Sunday, loud keyboard.',
            'Why do laundry baskets refill themselves?',
            'Trying to post less and say more.',
            'Code review tip: ask why before how.',
            'Caught a great sunset from the train.',
            'New desk plant. Name suggestions welcome.',
            'Spent an hour renaming variables. No regrets.',
            'Street food > fine dining most weeknights.',
            'Reminder: ship, then iterate.',
            'Old camera, new photos. Film grain forever.',
            'That bug was a missing semicolon of the soul.',
            'Walking meetings are underrated.',
            'Made cold brew overnight. Patience pays.',
            'Reading fiction again after months of docs only.',
            'Small wins compound. Logged three today.',
            'Clouds look painted tonight.',
            'If your API needs a novel of docs, simplify the API.',
        ];

        $createdAts = [
            now()->subHours(1),
            now()->subHours(3),
            now()->subHours(6),
            now()->subHours(12),
            now()->subHours(18),
            now()->subDay(),
            now()->subDays(2),
            now()->subDays(3),
            now()->subDays(4),
            now()->subDays(5),
            now()->subDays(6),
            now()->subWeek(),
            now()->subDays(10),
            now()->subDays(12),
            now()->subDays(14),
            now()->subWeeks(2),
            now()->subWeeks(3),
            now()->subDays(25),
            now()->subMonth(),
            now()->subDays(2)->subHours(4),
            now()->subHours(8),
            now()->subDays(1)->subHours(5),
            now()->subDays(7)->subHours(2),
            now()->subDays(9),
            now()->subDays(15),
            now()->subHours(2),
            now()->subDays(3)->subHours(7),
            now()->subWeeks(2)->subDays(1),
            now()->subDays(20),
            now()->subHours(14),
            now()->subDays(8),
            now()->subDays(11),
            now()->subHours(22),
            now()->subDays(16),
            now()->subDays(18),
            now()->subHours(4),
            now()->subDays(13),
            now()->subWeeks(3)->subDays(2),
            now()->subDays(21),
            now()->subHours(9),
        ];

        $authors = collect()
            ->merge($aliceFavorites->flatMap(fn (User $user) => array_fill(0, 5, $user)))
            ->merge([$bob, $bob, $bob, $alice, $alice])
            ->merge($otherUsers->take(10))
            ->merge($randomUsers->random(5))
            ->values();

        $posts = collect();

        foreach (range(0, 39) as $i) {
            $author = $authors[$i % $authors->count()];
            $createdAt = Carbon::parse($createdAts[$i])->utc();

            $posts->push(Post::query()->create([
                'user_id' => $author->id,
                'text' => $postBodies[$i],
                'image_url' => $i % 5 === 0 ? "https://picsum.photos/seed/guisedup{$i}/800/600" : null,
                'embedding' => null,
                'authenticity_score' => round(fake()->randomFloat(2, 0.2, 0.95), 2),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]));
        }

        // Alice follows her favorites (heavy), Bob, and a few others.
        foreach ($aliceFavorites as $favorite) {
            Follow::query()->create([
                'follower_id' => $alice->id,
                'followee_id' => $favorite->id,
                'created_at' => now()->subDays(fake()->numberBetween(7, 30)),
            ]);
        }

        Follow::query()->create([
            'follower_id' => $alice->id,
            'followee_id' => $bob->id,
            'created_at' => now()->subDays(14),
        ]);

        foreach ($otherUsers->random(4) as $user) {
            Follow::query()->create([
                'follower_id' => $alice->id,
                'followee_id' => $user->id,
                'created_at' => now()->subDays(fake()->numberBetween(1, 20)),
            ]);
        }

        // Broader follow graph among random users + Bob.
        Follow::query()->create([
            'follower_id' => $bob->id,
            'followee_id' => $alice->id,
            'created_at' => now()->subDays(10),
        ]);

        foreach ($randomUsers as $user) {
            $candidates = $allUsers->where('id', '!=', $user->id)->random(fake()->numberBetween(2, 5));

            foreach ($candidates as $followee) {
                Follow::query()->firstOrCreate(
                    [
                        'follower_id' => $user->id,
                        'followee_id' => $followee->id,
                    ],
                    [
                        'created_at' => now()->subDays(fake()->numberBetween(1, 40)),
                    ]
                );
            }
        }

        $interactionTypes = ['view', 'reply', 'reaction'];
        $favoritePostIds = $posts
            ->filter(fn (Post $post) => $aliceFavorites->pluck('id')->contains($post->user_id))
            ->pluck('id')
            ->values();

        // Alice interacts heavily with her favorite authors' posts.
        foreach ($favoritePostIds as $postId) {
            foreach (['view', 'view', 'reaction', 'reply'] as $type) {
                Interaction::query()->create([
                    'user_id' => $alice->id,
                    'post_id' => $postId,
                    'type' => $type,
                    'created_at' => now()->subHours(fake()->numberBetween(1, 72)),
                ]);
            }
        }

        // Alice lightly interacts with Bob's posts.
        foreach ($posts->where('user_id', $bob->id) as $post) {
            Interaction::query()->create([
                'user_id' => $alice->id,
                'post_id' => $post->id,
                'type' => 'view',
                'created_at' => now()->subHours(fake()->numberBetween(1, 48)),
            ]);
        }

        // Realistic interactions across the rest of the graph.
        foreach ($posts as $post) {
            $interactors = $allUsers
                ->where('id', '!=', $post->user_id)
                ->random(fake()->numberBetween(3, 8));

            foreach ($interactors as $user) {
                $type = fake()->randomElement($interactionTypes);

                // Bias views higher than replies/reactions.
                if (fake()->boolean(60)) {
                    $type = 'view';
                }

                Interaction::query()->create([
                    'user_id' => $user->id,
                    'post_id' => $post->id,
                    'type' => $type,
                    'created_at' => Carbon::parse($post->created_at)
                        ->addMinutes(fake()->numberBetween(5, 60 * 24 * 3)),
                ]);
            }
        }

        $this->backfillEmbeddings();
        $this->ensureEmbeddingIndex();
    }

    private function backfillEmbeddings(): void
    {
        $embeddings = app(EmbeddingService::class);

        Post::query()->whereNull('embedding')->each(function (Post $post) use ($embeddings): void {
            try {
                $vector = $embeddings->embed($post->text);
                $literal = $embeddings->toPgVectorLiteral($vector);

                DB::update(
                    'UPDATE posts SET embedding = CAST(? AS vector), updated_at = NOW() WHERE id = ?',
                    [$literal, $post->id]
                );
            } catch (\Throwable) {
                // Embedding service may be offline during seed; run: php artisan embeddings:backfill
            }
        });
    }

    private function ensureEmbeddingIndex(): void
    {
        $postCount = DB::table('posts')->count();

        if ($postCount === 0) {
            return;
        }

        try {
            DB::statement('
                CREATE INDEX IF NOT EXISTS posts_embedding_ivfflat_idx
                ON posts
                USING ivfflat (embedding vector_cosine_ops)
                WITH (lists = 100)
            ');
        } catch (\Throwable) {
            // IVFFlat may refuse empty/null embedding sets; safe to skip until embeddings exist.
        }
    }
}
