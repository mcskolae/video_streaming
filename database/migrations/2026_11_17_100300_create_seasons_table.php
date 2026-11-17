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
        Schema::create('seasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('title_id')->constrained('titles')->cascadeOnDelete();
            $table->smallInteger('number');
            $table->string('name', 255)->default('');
            $table->text('overview')->default('');
            $table->timestampsTz();

            $table->unique(['title_id', 'number']);
        });

        // Le numéro 0 désigne les épisodes spéciaux.
        DB::statement('ALTER TABLE seasons ADD CONSTRAINT seasons_number_range CHECK (number BETWEEN 0 AND 999)');
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
