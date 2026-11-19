<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI\View;

use HomeFlix\Catalog\Application\Query\Public\PublicTitleDetails;
use HomeFlix\Catalog\Domain\Model\TitleType;

/**
 * Données structurées schema.org (JSON-LD) d'une fiche : elles disent aux moteurs de recherche
 * que la page décrit un film ou une série, avec son titre, son année, ses genres et sa durée.
 */
final class TitleStructuredData
{
    /** @return array<string, mixed> */
    public static function of(PublicTitleDetails $title, string $url): array
    {
        $isSeries = $title->type === TitleType::Series;
        // La saison 0 regroupe les épisodes spéciaux : ce n'est pas une saison.
        $seasons = array_filter($title->seasons, fn ($season) => $season->number > 0);
        $genres = array_map(fn ($genre) => $genre->name, $title->genres);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $isSeries ? 'TVSeries' : 'Movie',
            'name' => $title->name,
            'alternateName' => $title->originalName !== null && $title->originalName !== $title->name ? $title->originalName : null,
            'description' => $title->overview !== '' ? $title->overview : null,
            'url' => $url,
            'image' => PosterUrl::of($title->posterPath, 'w500'),
            'datePublished' => $title->releaseYear !== null ? (string) $title->releaseYear : null,
            'genre' => $genres !== [] ? $genres : null,
            'duration' => ! $isSeries && $title->runtimeMinutes !== null ? 'PT'.$title->runtimeMinutes.'M' : null,
            'numberOfSeasons' => $isSeries ? count($seasons) : null,
            'numberOfEpisodes' => $isSeries ? array_sum(array_map(fn ($season) => count($season->episodes), $seasons)) : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * JSON à placer dans une balise <script type="application/ld+json">. Les caractères < > & sont
     * encodés en séquences Unicode : un titre venu de TMDB ne peut pas fermer la balise et injecter du HTML.
     */
    public static function json(PublicTitleDetails $title, string $url): string
    {
        return json_encode(self::of($title, $url), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
