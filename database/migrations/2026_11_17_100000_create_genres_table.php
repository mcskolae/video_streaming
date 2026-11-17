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
        // Recherche tolérante aux fautes de frappe (carte C5).
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('genres', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('tmdb_id')->unique();
            $table->string('name', 100);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genres');
    }
};
