<?php

declare(strict_types=1);

namespace Tests\Support\Tmdb;

/** Réponses TMDB réalistes (inventées) pour les tests. */
final class TmdbFixtures
{
    /** @return array<string, mixed> */
    public static function movie(): array
    {
        return [
            'id' => 10378,
            'title' => 'Big Buck Bunny',
            'original_title' => 'Big Buck Bunny',
            'overview' => 'Un lapin géant se venge de trois rongeurs.',
            'release_date' => '2008-04-10',
            'runtime' => 10,
            'poster_path' => '/poster.jpg',
            'backdrop_path' => '/backdrop.jpg',
            'genres' => [['id' => 16, 'name' => 'Animation'], ['id' => 35, 'name' => 'Comédie']],
            'vote_average' => 6.5,
        ];
    }

    /** @return array<string, mixed> */
    public static function series(): array
    {
        return [
            'id' => 1399,
            'name' => 'Sintel Chronicles',
            'original_name' => 'Sintel Chronicles',
            'overview' => 'Une guerrière cherche son dragon.',
            'first_air_date' => '2010-09-27',
            'episode_run_time' => [24],
            'poster_path' => '/series-poster.jpg',
            'backdrop_path' => '/series-backdrop.jpg',
            'genres' => [['id' => 16, 'name' => 'Animation']],
            'seasons' => [
                ['season_number' => 0, 'name' => 'Épisodes spéciaux', 'overview' => ''],
                ['season_number' => 1, 'name' => 'Saison 1', 'overview' => 'Premiers pas.'],
                ['season_number' => 2, 'name' => 'Saison 2', 'overview' => ''],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function season(int $number): array
    {
        return [
            'season_number' => $number,
            'name' => $number === 0 ? 'Épisodes spéciaux' : "Saison {$number}",
            'overview' => "Résumé de la saison {$number}.",
            'episodes' => [
                ['episode_number' => 1, 'name' => 'Pilote', 'overview' => 'Début.', 'runtime' => 24, 'still_path' => '/still-1.jpg'],
                ['episode_number' => 2, 'name' => 'Suite', 'overview' => 'La suite.', 'runtime' => null, 'still_path' => null],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function movieSearch(): array
    {
        return [
            'page' => 1,
            'results' => [
                ['id' => 10378, 'title' => 'Big Buck Bunny', 'original_title' => 'Big Buck Bunny', 'overview' => 'Un lapin.', 'release_date' => '2008-04-10', 'poster_path' => '/poster.jpg'],
                ['id' => 45, 'title' => 'Sans date', 'original_title' => 'Sans date', 'overview' => '', 'release_date' => '', 'poster_path' => null],
            ],
            'total_results' => 2,
        ];
    }
}
