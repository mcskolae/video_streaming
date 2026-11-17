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
        Schema::create('watch_progress', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->unsignedInteger('position_seconds');
            $table->unsignedInteger('duration_seconds');
            $table->boolean('completed')->default(false);
            $table->timestampTz('updated_at')->useCurrent();

            // Une progression par membre et par vidéo.
            $table->primary(['user_id', 'media_asset_id']);
            $table->index(['user_id', 'updated_at']);
        });

        DB::statement('ALTER TABLE watch_progress ADD CONSTRAINT watch_progress_position_within_duration CHECK (position_seconds <= duration_seconds)');
        DB::statement('ALTER TABLE watch_progress ADD CONSTRAINT watch_progress_duration_positive CHECK (duration_seconds >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_progress');
    }
};
