<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * IVFFlat index creation can fail on an empty table, so only create
     * the index when posts already exist.
     */
    public function up(): void
    {
        $postCount = DB::table('posts')->count();

        if ($postCount === 0) {
            return;
        }

        DB::statement('
            CREATE INDEX IF NOT EXISTS posts_embedding_ivfflat_idx
            ON posts
            USING ivfflat (embedding vector_cosine_ops)
            WITH (lists = 100)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS posts_embedding_ivfflat_idx');
    }
};
