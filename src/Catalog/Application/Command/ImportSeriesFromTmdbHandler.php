<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Command;

use HomeFlix\Catalog\Application\Exception\MetadataNotFound;
use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Application\Metadata\SeriesMetadata;
use HomeFlix\Catalog\Application\Port\MetadataProvider;
use HomeFlix\Catalog\Domain\Model\EpisodeId;
use HomeFlix\Catalog\Domain\Model\SeasonId;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TitleId;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Shared\Application\EventDispatcher;
use HomeFlix\Shared\Application\IdGenerator;
use InvalidArgumentException;

final readonly class ImportSeriesFromTmdbHandler
{
    public function __construct(
        private TitleRepository $titles,
        private MetadataProvider $metadata,
        private IdGenerator $ids,
        private EventDispatcher $events,
    ) {}

    /**
     * Une série déjà importée n'est jamais dupliquée : sa fiche, ses saisons et ses épisodes sont mis à jour
     * (par numéro), son état et les médias déjà prêts sont conservés. Un épisode que TMDB ne liste plus reste
     * au catalogue : on ne supprime jamais un média.
     *
     * @throws InvalidArgumentException si l'identifiant TMDB n'est pas valide
     * @throws MetadataNotFound
     * @throws MetadataProviderUnavailable
     */
    public function handle(ImportSeriesFromTmdb $command): ImportSeriesResult
    {
        $tmdbId = new TmdbId($command->tmdbId);
        $series = $this->metadata->series($tmdbId);
        $title = $this->titles->findByTmdbId(TitleType::Series, $tmdbId);
        $created = $title === null;

        if ($title === null) {
            $title = Title::importSeries(
                TitleId::fromString($this->ids->generate()),
                $tmdbId,
                $this->uniqueSlug($series->details, $tmdbId),
                $series->details,
            );
        } else {
            $title->refreshDetails($series->details);
        }

        $this->applySeasons($title, $series);

        $this->titles->save($title);
        $this->events->dispatch(...$title->pullDomainEvents());

        return new ImportSeriesResult($title->id(), $created);
    }

    private function applySeasons(Title $title, SeriesMetadata $series): void
    {
        foreach ($series->seasons as $seasonData) {
            $season = $title->upsertSeason(
                SeasonId::fromString($this->ids->generate()),
                $seasonData->number,
                $seasonData->name,
                $seasonData->overview,
            );

            foreach ($seasonData->episodes as $episodeData) {
                $season->upsertEpisode(
                    EpisodeId::fromString($this->ids->generate()),
                    $episodeData->number,
                    $episodeData->name,
                    $episodeData->overview,
                    $episodeData->runtimeMinutes,
                    $episodeData->stillPath,
                );
            }
        }
    }

    /** "nom-annee" si libre, sinon "nom-identifiantTmdb-annee", sinon "serie-identifiantTmdb". */
    private function uniqueSlug(TitleDetails $details, TmdbId $tmdbId): Slug
    {
        try {
            $slug = Slug::fromTitle($details->name, $details->releaseYear);

            if ($this->titles->findBySlug($slug) === null) {
                return $slug;
            }

            return Slug::fromTitle($details->name.' '.$tmdbId->value, $details->releaseYear);
        } catch (InvalidArgumentException) {
            return Slug::fromString('serie-'.$tmdbId->value);
        }
    }
}
