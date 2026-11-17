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
        Schema::create('titles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['movie', 'series']);
            $table->unsignedInteger('tmdb_id');
            $table->string('slug', 120)->unique();
            $table->string('title', 255);
            $table->string('original_title', 255)->nullable();
            $table->text('overview')->default('');
            $table->smallInteger('release_year')->nullable();
            $table->smallInteger('runtime_minutes')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestampTz('published_at')->nullable();
            // Film : média prêt. Toujours faux pour une série (lisible via ses épisodes).
            $table->boolean('is_playable')->default(false);
            $table->timestampsTz();

            // Un même film ou une même série TMDB n'existe qu'une fois.
            $table->unique(['type', 'tmdb_id']);
            $table->index(['status', 'type']);
        });

        DB::statement('ALTER TABLE titles ADD CONSTRAINT titles_release_year_range CHECK (release_year IS NULL OR release_year BETWEEN 1870 AND 2100)');
        DB::statement('ALTER TABLE titles ADD CONSTRAINT titles_runtime_positive CHECK (runtime_minutes IS NULL OR runtime_minutes BETWEEN 1 AND 10000)');
        // Publié si et seulement si une date de publication existe.
        DB::statement("ALTER TABLE titles ADD CONSTRAINT titles_published_has_date CHECK ((status = 'published') = (published_at IS NOT NULL))");
        // Un film publié est toujours lisible. Les séries le sont via leurs épisodes (règle portée par le domaine).
        DB::statement("ALTER TABLE titles ADD CONSTRAINT titles_published_movie_playable CHECK (type <> 'movie' OR status <> 'published' OR is_playable)");
        DB::statement("ALTER TABLE titles ADD CONSTRAINT titles_series_not_flagged CHECK (type <> 'series' OR NOT is_playable)");
        DB::statement('CREATE INDEX titles_title_trgm_idx ON titles USING gin (title gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('titles');
    }
};
