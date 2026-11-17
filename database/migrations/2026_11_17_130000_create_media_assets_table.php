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
        Schema::create('media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Un média illustre un film (title_id) OU un épisode (episode_id), jamais les deux, jamais aucun.
            $table->foreignUuid('title_id')->nullable()->unique()->constrained('titles')->cascadeOnDelete();
            $table->foreignUuid('episode_id')->nullable()->unique()->constrained('episodes')->cascadeOnDelete();
            $table->string('source_path', 255);
            $table->enum('status', ['uploaded', 'queued', 'processing', 'ready', 'failed'])->default('uploaded');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->string('hls_master_path', 255)->nullable();
            $table->timestampsTz();

            $table->index('status');
        });

        DB::statement('ALTER TABLE media_assets ADD CONSTRAINT media_assets_target_exactly_one CHECK ((title_id IS NULL) <> (episode_id IS NULL))');
        // Un média prêt a une durée et un flux ; seul un média en échec porte un message d'erreur.
        DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_ready_has_stream CHECK (status <> 'ready' OR (duration_seconds IS NOT NULL AND hls_master_path IS NOT NULL))");
        DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_error_only_when_failed CHECK (error_message IS NULL OR status = 'failed')");
        DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_failed_has_error CHECK (status <> 'failed' OR error_message IS NOT NULL)");
        DB::statement('ALTER TABLE media_assets ADD CONSTRAINT media_assets_duration_range CHECK (duration_seconds IS NULL OR duration_seconds BETWEEN 1 AND 86400)');
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
