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
        Schema::create('renditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('label', 10);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('video_bitrate_kbps');
            $table->string('playlist_path', 255);
            $table->timestampsTz();

            $table->unique(['media_asset_id', 'label']);
        });

        DB::statement('ALTER TABLE renditions ADD CONSTRAINT renditions_size_range CHECK (width BETWEEN 1 AND 8192 AND height BETWEEN 1 AND 8192)');
        DB::statement('ALTER TABLE renditions ADD CONSTRAINT renditions_bitrate_range CHECK (video_bitrate_kbps BETWEEN 1 AND 100000)');
    }

    public function down(): void
    {
        Schema::dropIfExists('renditions');
    }
};
