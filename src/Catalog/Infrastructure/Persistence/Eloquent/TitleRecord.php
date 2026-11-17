<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Persistence\Eloquent;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ligne de la table `titles`. Détail d'infrastructure : le domaine ne la connaît pas.
 *
 * @property string $id
 * @property string $type
 * @property int $tmdb_id
 * @property string $slug
 * @property string $title
 * @property string|null $original_title
 * @property string $overview
 * @property int|null $release_year
 * @property int|null $runtime_minutes
 * @property string|null $poster_path
 * @property string|null $backdrop_path
 * @property string $status
 * @property CarbonImmutable|null $published_at
 * @property bool $is_playable
 */
final class TitleRecord extends Model
{
    protected $table = 'titles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'release_year' => 'integer',
            'runtime_minutes' => 'integer',
            'published_at' => 'immutable_datetime',
            'is_playable' => 'boolean',
        ];
    }

    /** @return BelongsToMany<GenreRecord, $this> */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(GenreRecord::class, 'title_genre', 'title_id', 'genre_id')->orderBy('tmdb_id');
    }

    /** @return HasMany<SeasonRecord, $this> */
    public function seasons(): HasMany
    {
        return $this->hasMany(SeasonRecord::class, 'title_id')->orderBy('number');
    }
}
