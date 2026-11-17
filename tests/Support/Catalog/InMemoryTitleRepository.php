<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use HomeFlix\Catalog\Domain\Model\EpisodeId;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Model\TitleId;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;

/**
 * Dépôt en mémoire qui copie les objets à l'entrée et à la sortie, comme une vraie base :
 * un handler qui oublie `save()` ne verrait pas sa modification. Les événements en attente ne sont pas stockés.
 */
final class InMemoryTitleRepository implements TitleRepository
{
    /** @var array<string, string> titres sérialisés par identifiant */
    private array $stored = [];

    public int $saves = 0;

    public function save(Title $title): void
    {
        // Comme une vraie base, on ne stocke pas les événements en attente : ils sont publiés par le handler.
        $copy = $this->copy(serialize($title));
        $copy->pullDomainEvents();

        $this->stored[$title->id()->value] = serialize($copy);
        $this->saves++;
    }

    public function find(TitleId $id): ?Title
    {
        return isset($this->stored[$id->value]) ? $this->copy($this->stored[$id->value]) : null;
    }

    public function findByTmdbId(TitleType $type, TmdbId $tmdbId): ?Title
    {
        foreach ($this->all() as $title) {
            if ($title->type() === $type && $title->tmdbId()->equals($tmdbId)) {
                return $title;
            }
        }

        return null;
    }

    public function findBySlug(Slug $slug): ?Title
    {
        foreach ($this->all() as $title) {
            if ($title->slug()->equals($slug)) {
                return $title;
            }
        }

        return null;
    }

    public function findByEpisodeId(EpisodeId $id): ?Title
    {
        foreach ($this->all() as $title) {
            foreach ($title->seasons() as $season) {
                foreach ($season->episodes() as $episode) {
                    if ($episode->id->equals($id)) {
                        return $title;
                    }
                }
            }
        }

        return null;
    }

    /** @return list<Title> */
    public function all(): array
    {
        return array_values(array_map(fn (string $data) => $this->copy($data), $this->stored));
    }

    public function count(): int
    {
        return count($this->stored);
    }

    private function copy(string $data): Title
    {
        $title = unserialize($data);
        assert($title instanceof Title);

        return $title;
    }
}
