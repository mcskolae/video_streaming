<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $tmdb_id
 * @property string $name
 */
final class GenreRecord extends Model
{
    protected $table = 'genres';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tmdb_id' => 'integer'];
    }
}
