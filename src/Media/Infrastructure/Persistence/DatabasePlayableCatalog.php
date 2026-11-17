<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure\Persistence;

use HomeFlix\Media\Application\Port\PlayableCatalog;
use HomeFlix\Media\Domain\Model\PlayableKind;
use HomeFlix\Media\Domain\Model\PlayableRef;
use Illuminate\Support\Facades\DB;

/** Interroge les tables du catalogue. Un film est un titre de type "movie" ; un épisode est un épisode. */
final class DatabasePlayableCatalog implements PlayableCatalog
{
    public function exists(PlayableRef $target): bool
    {
        if ($target->kind === PlayableKind::Title) {
            return DB::table('titles')->where('id', $target->id->value)->where('type', 'movie')->exists();
        }

        return DB::table('episodes')->where('id', $target->id->value)->exists();
    }

    public function label(PlayableRef $target): ?string
    {
        if ($target->kind === PlayableKind::Title) {
            $name = DB::table('titles')->where('id', $target->id->value)->where('type', 'movie')->value('title');

            return $name === null ? null : (string) $name;
        }

        $row = DB::table('episodes')
            ->join('seasons', 'seasons.id', '=', 'episodes.season_id')
            ->join('titles', 'titles.id', '=', 'seasons.title_id')
            ->where('episodes.id', $target->id->value)
            ->first(['titles.title', 'seasons.number as season', 'episodes.number as episode', 'episodes.name']);

        if ($row === null) {
            return null;
        }

        return sprintf('%s, S%dE%d%s', $row->title, $row->season, $row->episode, $row->name !== '' ? ' : '.$row->name : '');
    }
}
