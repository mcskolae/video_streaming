<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Port;

use HomeFlix\Media\Domain\Model\MediaAssetId;
use HomeFlix\Media\Domain\Model\RelativePath;

/** Stockage des fichiers médias. Tous les chemins sont relatifs à une racine que seul l'adaptateur connaît. */
interface MediaStorage
{
    /** Chemin absolu d'un fichier source. */
    public function absolutePath(RelativePath $path): string;

    /** Dossier de sortie HLS d'un média, créé s'il n'existe pas : `hls/<id>`. Retourne son chemin absolu. */
    public function prepareHlsDirectory(MediaAssetId $id): string;

    /** Chemin relatif du dossier HLS d'un média : `hls/<id>`. */
    public function hlsRelativePath(MediaAssetId $id): string;

    /** Le fichier existe-t-il ? */
    public function exists(RelativePath $path): bool;

    public function deleteFile(RelativePath $path): void;

    /** Supprime le dossier HLS d'un média (après un échec, pour ne pas laisser de flux à moitié écrit). */
    public function deleteHlsDirectory(MediaAssetId $id): void;
}
