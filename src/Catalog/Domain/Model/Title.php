<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Model;

use DateTimeImmutable;
use HomeFlix\Catalog\Domain\Event\TitlePublished;
use HomeFlix\Catalog\Domain\Event\TitleUnpublished;
use HomeFlix\Catalog\Domain\Exception\SeasonAlreadyExists;
use HomeFlix\Catalog\Domain\Exception\SeasonNotFound;
use HomeFlix\Catalog\Domain\Exception\TitleCannotBePublished;
use HomeFlix\Catalog\Domain\Exception\UnsupportedForTitleType;
use HomeFlix\Shared\Domain\AggregateRoot;

/**
 * Film ou série du catalogue. Racine d'agrégat : les saisons et les épisodes
 * ne se modifient qu'à travers elle.
 *
 * Invariants :
 *  - un film n'a ni saison ni épisode ;
 *  - un titre publié est toujours lisible (au moins un média prêt) ;
 *  - un film est lisible quand son média est prêt, une série quand au moins
 *    un de ses épisodes l'est.
 */
final class Title extends AggregateRoot
{
    /** @var array<int, Season> saisons indexées par numéro */
    private array $seasons = [];

    /** @param  list<Season>  $seasons */
    private function __construct(
        private readonly TitleId $id,
        private readonly TitleType $type,
        private readonly TmdbId $tmdbId,
        private Slug $slug,
        private TitleDetails $details,
        private TitleStatus $status,
        private ?DateTimeImmutable $publishedAt,
        private bool $playable,
        array $seasons,
    ) {
        foreach ($seasons as $season) {
            if (isset($this->seasons[$season->number])) {
                throw SeasonAlreadyExists::withNumber($season->number);
            }

            $this->seasons[$season->number] = $season;
        }

        ksort($this->seasons);
    }

    /** Nouveau film importé : il démarre en brouillon et non lisible. */
    public static function importMovie(TitleId $id, TmdbId $tmdbId, Slug $slug, TitleDetails $details): self
    {
        return new self($id, TitleType::Movie, $tmdbId, $slug, $details, TitleStatus::Draft, null, false, []);
    }

    /** Nouvelle série importée : elle démarre en brouillon, sans saison. */
    public static function importSeries(TitleId $id, TmdbId $tmdbId, Slug $slug, TitleDetails $details): self
    {
        return new self($id, TitleType::Series, $tmdbId, $slug, $details, TitleStatus::Draft, null, false, []);
    }

    /**
     * Recharge un titre déjà persisté. N'enregistre aucun événement et ne revérifie
     * pas les transitions : l'état vient de la base.
     *
     * @param  list<Season>  $seasons
     */
    public static function reconstitute(
        TitleId $id,
        TitleType $type,
        TmdbId $tmdbId,
        Slug $slug,
        TitleDetails $details,
        TitleStatus $status,
        ?DateTimeImmutable $publishedAt,
        bool $playable,
        array $seasons,
    ): self {
        return new self($id, $type, $tmdbId, $slug, $details, $status, $publishedAt, $playable, $seasons);
    }

    public function id(): TitleId
    {
        return $this->id;
    }

    public function type(): TitleType
    {
        return $this->type;
    }

    public function tmdbId(): TmdbId
    {
        return $this->tmdbId;
    }

    public function slug(): Slug
    {
        return $this->slug;
    }

    public function details(): TitleDetails
    {
        return $this->details;
    }

    public function status(): TitleStatus
    {
        return $this->status;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isPublished(): bool
    {
        return $this->status === TitleStatus::Published;
    }

    /** Drapeau stocké : vrai pour un film dont le média est prêt, toujours faux pour une série. */
    public function playableFlag(): bool
    {
        return $this->playable;
    }

    public function isPlayable(): bool
    {
        if ($this->type === TitleType::Movie) {
            return $this->playable;
        }

        foreach ($this->seasons as $season) {
            if ($season->hasPlayableEpisode()) {
                return true;
            }
        }

        return false;
    }

    /** Met à jour les informations descriptives (réimport TMDB). Le slug et l'état ne changent pas. */
    public function refreshDetails(TitleDetails $details): void
    {
        $this->details = $details;
    }

    public function addSeason(SeasonId $id, int $number, string $name = '', string $overview = ''): Season
    {
        $this->assertSeries();

        if (isset($this->seasons[$number])) {
            throw SeasonAlreadyExists::withNumber($number);
        }

        $season = new Season($id, $number, $name, $overview);
        $this->seasons[$number] = $season;
        ksort($this->seasons);

        return $season;
    }

    public function addEpisode(
        int $seasonNumber,
        EpisodeId $id,
        int $number,
        string $name,
        string $overview = '',
        ?int $runtimeMinutes = null,
        ?string $stillPath = null,
    ): Episode {
        $this->assertSeries();

        $episode = new Episode($id, $number, $name, $overview, $runtimeMinutes, $stillPath);
        $this->season($seasonNumber)->addEpisode($episode);

        return $episode;
    }

    public function season(int $number): Season
    {
        return $this->seasons[$number] ?? throw SeasonNotFound::withNumber($number);
    }

    /** @return list<Season> */
    public function seasons(): array
    {
        return array_values($this->seasons);
    }

    /** Le média du film est prêt. */
    public function markPlayable(): void
    {
        $this->assertMovie();
        $this->playable = true;
    }

    /** Le média du film n'est plus prêt. Un film publié repasse alors en brouillon. */
    public function markUnplayable(DateTimeImmutable $at): void
    {
        $this->assertMovie();
        $this->playable = false;
        $this->unpublishIfNoLongerPlayable($at);
    }

    public function markEpisodePlayable(int $seasonNumber, int $episodeNumber): void
    {
        $this->assertSeries();
        $this->season($seasonNumber)->episode($episodeNumber)->markPlayable();
    }

    /** Le média d'un épisode n'est plus prêt. Une série publiée sans épisode lisible repasse en brouillon. */
    public function markEpisodeUnplayable(int $seasonNumber, int $episodeNumber, DateTimeImmutable $at): void
    {
        $this->assertSeries();
        $this->season($seasonNumber)->episode($episodeNumber)->markUnplayable();
        $this->unpublishIfNoLongerPlayable($at);
    }

    /** Publie le titre. Sans effet s'il l'est déjà. */
    public function publish(DateTimeImmutable $at): void
    {
        if ($this->isPublished()) {
            return;
        }

        if (! $this->isPlayable()) {
            throw TitleCannotBePublished::notPlayable($this->id);
        }

        $this->status = TitleStatus::Published;
        $this->publishedAt = $at;
        $this->record(new TitlePublished($this->id, $at));
    }

    /** Repasse le titre en brouillon. Sans effet s'il n'est pas publié. */
    public function unpublish(DateTimeImmutable $at): void
    {
        if (! $this->isPublished()) {
            return;
        }

        $this->status = TitleStatus::Draft;
        $this->publishedAt = null;
        $this->record(new TitleUnpublished($this->id, $at));
    }

    private function unpublishIfNoLongerPlayable(DateTimeImmutable $at): void
    {
        if (! $this->isPlayable()) {
            $this->unpublish($at);
        }
    }

    private function assertMovie(): void
    {
        if ($this->type !== TitleType::Movie) {
            throw UnsupportedForTitleType::playableFlagOnSeries();
        }
    }

    private function assertSeries(): void
    {
        if ($this->type !== TitleType::Series) {
            throw UnsupportedForTitleType::seasonsOnMovie();
        }
    }
}
