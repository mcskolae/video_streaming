<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('title_genre', function (Blueprint $table) {
            $table->foreignUuid('title_id')->constrained('titles')->cascadeOnDelete();
            $table->foreignUuid('genre_id')->constrained('genres')->restrictOnDelete();
            $table->primary(['title_id', 'genre_id']);
            $table->index('genre_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('title_genre');
    }
};
