<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('title_id')->constrained('titles')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            // Un favori est unique par utilisateur et par titre : l'ajouter deux fois ne change rien.
            $table->primary(['user_id', 'title_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
