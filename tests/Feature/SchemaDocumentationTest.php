<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

/**
 * Le schéma de docs/BASE-DE-DONNEES.md doit correspondre aux migrations : toute table et toute colonne
 * du diagramme existe dans la base, et toute colonne de ces tables figure dans le diagramme.
 *
 * @return array<string, list<string>> table => colonnes
 */
function documentedSchema(): array
{
    $markdown = (string) file_get_contents(dirname(__DIR__, 2).'/docs/BASE-DE-DONNEES.md');

    preg_match('/```mermaid\s+erDiagram(.*?)```/s', $markdown, $diagram);

    $tables = [];

    preg_match_all('/^\s{4}(\w+) \{\n(.*?)^\s{4}\}/ms', $diagram[1] ?? '', $blocks, PREG_SET_ORDER);

    foreach ($blocks as [, $table, $body]) {
        preg_match_all('/^\s+\w+ (\w+)/m', $body, $columns);
        $tables[$table] = $columns[1];
    }

    return $tables;
}

it('documents the tables of the application', function () {
    expect(array_keys(documentedSchema()))->toEqualCanonicalizing([
        'users', 'genres', 'titles', 'title_genre', 'seasons', 'episodes', 'media_assets', 'renditions', 'favorites', 'watch_progress',
    ]);
});

it('only documents tables and columns that exist', function (string $table, array $columns) {
    expect(Schema::hasTable($table))->toBeTrue();

    foreach ($columns as $column) {
        expect(Schema::hasColumn($table, $column))->toBeTrue("{$table}.{$column} n'existe pas");
    }
})->with(fn () => collect(documentedSchema())->map(fn (array $columns, string $table) => [$table, $columns])->all());

it('documents every column of these tables', function (string $table, array $columns) {
    // `id` des tables de liaison, colonnes ajoutées par Laravel à `users` : tout doit figurer au schéma.
    expect(Schema::getColumnListing($table))->toEqualCanonicalizing($columns);
})->with(fn () => collect(documentedSchema())->map(fn (array $columns, string $table) => [$table, $columns])->all());

it('lists in the table of constraints only constraints that exist', function () {
    $markdown = (string) file_get_contents(dirname(__DIR__, 2).'/docs/BASE-DE-DONNEES.md');
    preg_match_all('/`((?:users|titles|seasons|episodes|media_assets|renditions|favorites|watch_progress)_[a-z_]+)`/', $markdown, $names);

    $existing = collect(DB::select("select conname from pg_constraint where connamespace = 'public'::regnamespace"))->pluck('conname');

    foreach (array_unique($names[1]) as $name) {
        expect($existing->contains($name))->toBeTrue("la contrainte {$name} n'existe pas");
    }
});
