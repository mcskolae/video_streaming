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
        Schema::create('episodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->smallInteger('number');
            $table->string('name', 255)->default('');
            $table->text('overview')->default('');
            $table->smallInteger('runtime_minutes')->nullable();
            $table->string('still_path')->nullable();
            $table->boolean('is_playable')->default(false);
            $table->timestampsTz();

            $table->unique(['season_id', 'number']);
        });

        DB::statement('ALTER TABLE episodes ADD CONSTRAINT episodes_number_positive CHECK (number >= 1)');
        DB::statement('ALTER TABLE episodes ADD CONSTRAINT episodes_runtime_positive CHECK (runtime_minutes IS NULL OR runtime_minutes BETWEEN 1 AND 10000)');
    }

    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};
