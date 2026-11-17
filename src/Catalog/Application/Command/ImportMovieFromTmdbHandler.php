<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Command;

use HomeFlix\Catalog\Application\Exception\MetadataNotFound;
use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Application\Port\MetadataProvider;
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

final readonly class ImportMovieFromTmdbHandler
{
    public function __construct(
        private TitleRepository $titles,
        private MetadataProvider $metadata,
        private IdGenerator $ids,
        private EventDispatcher $events,
    ) {}

    /**
     * Un film déjà importé n'est jamais dupliqué : sa fiche est mise à jour, son état (brouillon ou publié) est conservé.
     *
     * @throws InvalidArgumentException si l'identifiant TMDB n'est pas valide
     * @throws MetadataNotFound
     * @throws MetadataProviderUnavailable
     */
    public function handle(ImportMovieFromTmdb $command): ImportMovieResult
    {
        $tmdbId = new TmdbId($command->tmdbId);
        $movie = $this->metadata->movie($tmdbId);
        $existing = $this->titles->findByTmdbId(TitleType::Movie, $tmdbId);

        if ($existing !== null) {
            $existing->refreshDetails($movie->details);
            $this->titles->save($existing);
            $this->events->dispatch(...$existing->pullDomainEvents());

            return new ImportMovieResult($existing->id(), created: false);
        }

        $title = Title::importMovie(
            TitleId::fromString($this->ids->generate()),
            $tmdbId,
            $this->uniqueSlug($movie->details, $tmdbId),
            $movie->details,
        );

        $this->titles->save($title);
        $this->events->dispatch(...$title->pullDomainEvents());

        return new ImportMovieResult($title->id(), created: true);
    }

    /**
     * "nom-annee" si libre, sinon "nom-identifiantTmdb-annee". Un titre sans lettre ni chiffre
     * (par exemple "???") reçoit "film-identifiantTmdb".
     */
    private function uniqueSlug(TitleDetails $details, TmdbId $tmdbId): Slug
    {
        try {
            $slug = Slug::fromTitle($details->name, $details->releaseYear);

            if ($this->titles->findBySlug($slug) === null) {
                return $slug;
            }

            return Slug::fromTitle($details->name.' '.$tmdbId->value, $details->releaseYear);
        } catch (InvalidArgumentException) {
            return Slug::fromString('film-'.$tmdbId->value);
        }
    }
}
