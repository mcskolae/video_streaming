<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use HomeFlix\Catalog\Application\Exception\MetadataNotFound;
use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Application\Metadata\MovieMetadata;
use HomeFlix\Catalog\Application\Metadata\SearchResult;
use HomeFlix\Catalog\Application\Metadata\SeriesMetadata;
use HomeFlix\Catalog\Application\Port\MetadataProvider;
use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;

final class FakeMetadataProvider implements MetadataProvider
{
    /** @var array<int, MovieMetadata> */
    private array $movies = [];

    /** @var list<SearchResult> */
    public array $searchResults = [];

    public bool $unavailable = false;

    public int $calls = 0;

    public function withMovie(int $tmdbId, TitleDetails $details): self
    {
        $this->movies[$tmdbId] = new MovieMetadata(new TmdbId($tmdbId), $details);

        return $this;
    }

    public function search(string $query, TitleType $type): array
    {
        $this->calls++;
        $this->failIfUnavailable();

        return $this->searchResults;
    }

    public function movie(TmdbId $id): MovieMetadata
    {
        $this->calls++;
        $this->failIfUnavailable();

        return $this->movies[$id->value] ?? throw MetadataNotFound::forTitle(TitleType::Movie, $id);
    }

    public function series(TmdbId $id): SeriesMetadata
    {
        throw MetadataNotFound::forTitle(TitleType::Series, $id);
    }

    private function failIfUnavailable(): void
    {
        if ($this->unavailable) {
            throw MetadataProviderUnavailable::unreachable();
        }
    }
}
