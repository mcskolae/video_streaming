<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use HomeFlix\Catalog\Domain\Model\EpisodeId;
use HomeFlix\Catalog\Domain\Model\Genre;
use HomeFlix\Catalog\Domain\Model\SeasonId;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TitleId;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use Ramsey\Uuid\Rfc4122\FieldsInterface;
use Ramsey\Uuid\Uuid;

/** Construit des objets du domaine Catalog pour les tests. */
final class CatalogFixtures
{
    public static function titleId(): TitleId
    {
        return TitleId::fromString(Uuid::uuid7()->toString());
    }

    public static function seasonId(): SeasonId
    {
        return SeasonId::fromString(Uuid::uuid7()->toString());
    }

    public static function episodeId(): EpisodeId
    {
        return EpisodeId::fromString(Uuid::uuid7()->toString());
    }

    /** Version RFC 4122 d'un UUID (7 attendu partout dans le projet), ou null s'il n'est pas RFC 4122. */
    public static function uuidVersion(string $uuid): ?int
    {
        $fields = Uuid::fromString($uuid)->getFields();

        return $fields instanceof FieldsInterface ? $fields->getVersion() : null;
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-17 12:00:00');
    }

    public static function details(string $name = 'Big Buck Bunny', int $year = 2008): TitleDetails
    {
        return new TitleDetails(
            name: $name,
            originalName: $name,
            overview: 'Un lapin géant se venge de trois rongeurs.',
            releaseYear: $year,
            runtimeMinutes: 10,
            posterPath: '/poster.jpg',
            backdropPath: '/backdrop.jpg',
            genres: [new Genre(16, 'Animation'), new Genre(35, 'Comédie')],
        );
    }

    public static function movie(int $tmdbId = 10378, string $name = 'Big Buck Bunny'): Title
    {
        return Title::importMovie(
            self::titleId(),
            new TmdbId($tmdbId),
            Slug::fromTitle($name, 2008),
            self::details($name),
        );
    }

    /** Série avec une saison de deux épisodes. */
    public static function series(int $tmdbId = 1399, string $name = 'Sintel Chronicles'): Title
    {
        $title = Title::importSeries(
            self::titleId(),
            new TmdbId($tmdbId),
            Slug::fromTitle($name, 2010),
            self::details($name, 2010),
        );
        $title->addSeason(self::seasonId(), 1, 'Saison 1', 'Premiers pas.');
        $title->addEpisode(1, self::episodeId(), 1, 'Pilote', 'Début.', 24, '/still1.jpg');
        $title->addEpisode(1, self::episodeId(), 2, 'Suite', 'La suite.', 25);

        return $title;
    }
}
