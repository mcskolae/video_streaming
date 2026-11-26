<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('title_genre', function (Blueprint $table) {
            // Genre principal du titre : le premier de la liste TMDB. Il décide de la rangée où le titre apparaît.
            $table->boolean('is_primary')->default(false);
        });

        // Un titre a au plus un genre principal.
        DB::statement('CREATE UNIQUE INDEX title_genre_one_primary ON title_genre (title_id) WHERE is_primary');

        // Titres déjà importés : l'ordre TMDB n'était pas conservé. Le genre au plus petit identifiant TMDB devient
        // principal ; réimporter un titre remplace ce choix par l'ordre TMDB.
        DB::statement(<<<'SQL'
            UPDATE title_genre SET is_primary = true
            FROM (
                SELECT DISTINCT ON (tg.title_id) tg.title_id, tg.genre_id
                FROM title_genre tg
                JOIN genres g ON g.id = tg.genre_id
                ORDER BY tg.title_id, g.tmdb_id
            ) pick
            WHERE title_genre.title_id = pick.title_id AND title_genre.genre_id = pick.genre_id
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS title_genre_one_primary');

        Schema::table('title_genre', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
