<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Transcoding;

use InvalidArgumentException;

/**
 * Choisit les qualités à produire d'après la taille de la source.
 *
 * 1080p et 720p, jamais au-dessus de la source (agrandir une image ne l'améliore pas).
 * Une source plus petite que 720p donne une seule qualité à sa propre hauteur.
 * Les dimensions sont paires, comme l'exige H.264.
 */
final class RenditionPlanner
{
    /** @var list<array{int, int}> hauteur, débit vidéo en kbit/s */
    private const LADDER = [[1080, 5000], [720, 2500]];

    private const FALLBACK_BITRATE_KBPS = 1200;

    private const MIN_HEIGHT = 144;

    /** @return non-empty-list<RenditionPlan> de la plus haute à la plus basse */
    public static function plan(int $sourceWidth, int $sourceHeight): array
    {
        if ($sourceWidth < 2 || $sourceHeight < 2 || $sourceWidth > 16384 || $sourceHeight > 16384) {
            throw new InvalidArgumentException(sprintf('Unusable source size %dx%d.', $sourceWidth, $sourceHeight));
        }

        $plans = [];

        foreach (self::LADDER as [$height, $bitrate]) {
            if ($height <= $sourceHeight) {
                $plans[] = self::rendition($height, $bitrate, $sourceWidth, $sourceHeight);
            }
        }

        if ($plans === []) {
            $plans[] = self::rendition(max(self::MIN_HEIGHT, self::even($sourceHeight)), self::FALLBACK_BITRATE_KBPS, $sourceWidth, $sourceHeight);
        }

        return $plans;
    }

    private static function rendition(int $height, int $bitrate, int $sourceWidth, int $sourceHeight): RenditionPlan
    {
        $width = max(2, self::even((int) round($sourceWidth * $height / $sourceHeight)));

        return new RenditionPlan($height.'p', $width, $height, $bitrate);
    }

    private static function even(int $value): int
    {
        return $value - ($value % 2);
    }
}
