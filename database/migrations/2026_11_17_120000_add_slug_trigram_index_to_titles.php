<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * La recherche publique compare aussi le slug, qui est en ASCII minuscule : "Amélie" et "amelie"
 * y sont identiques, ce qui rend la recherche insensible aux accents sans extension supplémentaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX titles_slug_trgm_idx ON titles USING gin (slug gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS titles_slug_trgm_idx');
    }
};
