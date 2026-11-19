<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI\View;

/**
 * URL d'une affiche TMDB. Défense en profondeur : même si une valeur étrange arrivait en base,
 * seul un chemin "/nom.ext" et une taille connue donnent une URL.
 */
final class PosterUrl
{
    public const SIZES = ['w92', 'w185', 'w342', 'w500'];

    public static function of(?string $path, string $size): ?string
    {
        if ($path === null || ! in_array($size, self::SIZES, true) || preg_match('#^/[A-Za-z0-9._-]{1,200}$#', $path) !== 1) {
            return null;
        }

        return config('services.tmdb.image_base_url').'/'.$size.$path;
    }
}
