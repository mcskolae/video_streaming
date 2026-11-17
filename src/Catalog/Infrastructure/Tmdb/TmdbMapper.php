<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Tmdb;

use HomeFlix\Catalog\Application\Metadata\EpisodeMetadata;
use HomeFlix\Catalog\Application\Metadata\MovieMetadata;
use HomeFlix\Catalog\Application\Metadata\SearchResult;
use HomeFlix\Catalog\Application\Metadata\SeasonMetadata;
use HomeFlix\Catalog\Application\Metadata\SeriesMetadata;
use HomeFlix\Catalog\Domain\Model\Genre;
use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use InvalidArgumentException;

/**
 * Transforme les réponses JSON de TMDB en objets de l'application.
 *
 * Les données viennent d'un service externe : tout est vérifié, et une donnée invalide
 * est ignorée (champ facultatif) ou refuse la réponse (champ indispensable), jamais
 * recopiée telle quelle.
 */
final class TmdbMapper
{
    /** Un chemin d'image TMDB est "/nom.jpg". Tout autre format (URL, chemin relatif) est refusé. */
    private const IMAGE_PATH = '#^/[A-Za-z0-9._-]{1,200}$#';

    private const MAX_SEARCH_RESULTS = 20;

    /**
     * @param  array<string, mixed>  $payload
     * @return list<SearchResult>
     */
    public static function searchResults(array $payload, TitleType $type): array
    {
        $results = [];

        foreach (self::list($payload['results'] ?? null) as $item) {
            $id = self::positiveInt($item['id'] ?? null);
            $name = self::text($item[$type === TitleType::Movie ? 'title' : 'name'] ?? null);

            if ($id === null || $name === null) {
                continue;
            }

            $results[] = new SearchResult(
                new TmdbId($id),
                $type,
                $name,
                self::text($item[$type === TitleType::Movie ? 'original_title' : 'original_name'] ?? null),
                self::string($item['overview'] ?? null),
                self::year($item[$type === TitleType::Movie ? 'release_date' : 'first_air_date'] ?? null),
                self::imagePath($item['poster_path'] ?? null),
            );

            if (count($results) === self::MAX_SEARCH_RESULTS) {
                break;
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException si la réponse n'a pas d'identifiant ou de titre
     */
    public static function movie(array $data): MovieMetadata
    {
        return new MovieMetadata(
            self::requiredId($data),
            new TitleDetails(
                name: self::text($data['title'] ?? null) ?? throw new InvalidArgumentException('A movie needs a title.'),
                originalName: self::text($data['original_title'] ?? null),
                overview: self::string($data['overview'] ?? null),
                releaseYear: self::year($data['release_date'] ?? null),
                runtimeMinutes: self::runtime($data['runtime'] ?? null),
                posterPath: self::imagePath($data['poster_path'] ?? null),
                backdropPath: self::imagePath($data['backdrop_path'] ?? null),
                genres: self::genres($data['genres'] ?? null),
            ),
        );
    }

    /**
     * Numéros de saison d'une série, triés et sans doublon. La saison 0 (épisodes spéciaux) est conservée.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    public static function seasonNumbers(array $data): array
    {
        $numbers = [];

        foreach (self::list($data['seasons'] ?? null) as $season) {
            $number = self::seasonNumber($season['season_number'] ?? null);

            if ($number !== null) {
                $numbers[$number] = $number;
            }
        }

        ksort($numbers);

        return array_values($numbers);
    }

    /**
     * @param  array<string, mixed>  $data  réponse de /tv/{id}
     * @param  array<int, array<string, mixed>|null>  $seasonDetails  réponses de /tv/{id}/season/{n}, indexées par numéro (null si TMDB ne les a pas)
     *
     * @throws InvalidArgumentException si la réponse n'a pas d'identifiant ou de nom
     */
    public static function series(array $data, array $seasonDetails): SeriesMetadata
    {
        $listed = [];

        foreach (self::list($data['seasons'] ?? null) as $season) {
            $number = self::seasonNumber($season['season_number'] ?? null);

            if ($number !== null) {
                $listed[$number] = $season;
            }
        }

        $seasons = [];

        foreach (self::seasonNumbers($data) as $number) {
            $details = $seasonDetails[$number] ?? null;
            // Si TMDB n'a pas le détail d'une saison, on garde ce que la série en dit, sans épisode.
            $source = $details ?? $listed[$number];

            $seasons[] = new SeasonMetadata(
                $number,
                self::string($source['name'] ?? null),
                self::string($source['overview'] ?? null),
                $details === null ? [] : self::episodes($details),
            );
        }

        $runtimes = self::list($data['episode_run_time'] ?? null, scalars: true);

        return new SeriesMetadata(
            self::requiredId($data),
            new TitleDetails(
                name: self::text($data['name'] ?? null) ?? throw new InvalidArgumentException('A series needs a name.'),
                originalName: self::text($data['original_name'] ?? null),
                overview: self::string($data['overview'] ?? null),
                releaseYear: self::year($data['first_air_date'] ?? null),
                runtimeMinutes: self::runtime($runtimes[0] ?? null),
                posterPath: self::imagePath($data['poster_path'] ?? null),
                backdropPath: self::imagePath($data['backdrop_path'] ?? null),
                genres: self::genres($data['genres'] ?? null),
            ),
            $seasons,
        );
    }

    /**
     * @param  array<string, mixed>  $season
     * @return list<EpisodeMetadata>
     */
    private static function episodes(array $season): array
    {
        $episodes = [];

        foreach (self::list($season['episodes'] ?? null) as $episode) {
            $number = self::positiveInt($episode['episode_number'] ?? null);

            if ($number === null || $number > 9999 || isset($episodes[$number])) {
                continue;
            }

            $episodes[$number] = new EpisodeMetadata(
                $number,
                mb_substr(self::string($episode['name'] ?? null), 0, 255),
                self::string($episode['overview'] ?? null),
                self::runtime($episode['runtime'] ?? null),
                self::imagePath($episode['still_path'] ?? null),
            );
        }

        ksort($episodes);

        return array_values($episodes);
    }

    /** @return list<Genre> */
    private static function genres(mixed $value): array
    {
        $genres = [];

        foreach (self::list($value) as $item) {
            $id = self::positiveInt($item['id'] ?? null);
            $name = self::text($item['name'] ?? null);

            if ($id !== null && $name !== null && mb_strlen($name) <= 100) {
                $genres[] = new Genre($id, $name);
            }
        }

        return $genres;
    }

    /** @param  array<string, mixed>  $data */
    private static function requiredId(array $data): TmdbId
    {
        $id = self::positiveInt($data['id'] ?? null);

        return $id === null ? throw new InvalidArgumentException('A TMDB response needs a positive id.') : new TmdbId($id);
    }

    /**
     * Les éléments d'une liste JSON qui sont eux-mêmes des objets (ou des scalaires si demandé).
     *
     * @return ($scalars is true ? list<mixed> : list<array<string, mixed>>)
     */
    private static function list(mixed $value, bool $scalars = false): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if ($scalars ? is_scalar($item) : is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private static function positiveInt(mixed $value): ?int
    {
        return is_int($value) && $value >= 1 ? $value : null;
    }

    private static function seasonNumber(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 && $value <= 999 ? $value : null;
    }

    private static function runtime(mixed $value): ?int
    {
        return is_int($value) && $value >= 1 && $value <= 10000 ? $value : null;
    }

    /** Texte non vide, ou null. */
    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' || mb_strlen($value) > 255 ? null : $value;
    }

    /** Texte libre (synopsis, titre d'épisode) : chaîne vide si absent. */
    private static function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /** Année d'une date "AAAA-MM-JJ", si elle est plausible. */
    private static function year(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^(\d{4})(?:-|$)/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[1];

        return $year >= 1870 && $year <= 2100 ? $year : null;
    }

    private static function imagePath(mixed $value): ?string
    {
        return is_string($value) && preg_match(self::IMAGE_PATH, $value) === 1 ? $value : null;
    }
}
