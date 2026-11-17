<?php

declare(strict_types=1);

namespace HomeFlix\Library\Infrastructure\Persistence;

use HomeFlix\Library\Domain\Model\Favorite;
use HomeFlix\Library\Domain\Model\MemberId;
use HomeFlix\Library\Domain\Model\TitleRef;
use HomeFlix\Library\Domain\Repository\FavoriteRepository;
use Illuminate\Support\Facades\DB;

final class DatabaseFavoriteRepository implements FavoriteRepository
{
    public function add(Favorite $favorite): void
    {
        // La clé primaire (membre, titre) rend l'ajout idempotent : un favori existant garde sa date d'origine.
        DB::table('favorites')->insertOrIgnore([
            'user_id' => $favorite->member->value,
            'title_id' => $favorite->title->value,
            'created_at' => $favorite->markedAt->format('Y-m-d H:i:s.uP'),
        ]);
    }

    public function remove(MemberId $member, TitleRef $title): void
    {
        DB::table('favorites')->where('user_id', $member->value)->where('title_id', $title->value)->delete();
    }

    public function exists(MemberId $member, TitleRef $title): bool
    {
        return DB::table('favorites')->where('user_id', $member->value)->where('title_id', $title->value)->exists();
    }
}
