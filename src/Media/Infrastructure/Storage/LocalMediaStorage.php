<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure\Storage;

use HomeFlix\Media\Application\Port\MediaStorage;
use HomeFlix\Media\Domain\Model\MediaAssetId;
use HomeFlix\Media\Domain\Model\RelativePath;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/** Stockage sur le disque local, sous une racine unique. */
final readonly class LocalMediaStorage implements MediaStorage
{
    public function __construct(private string $root, private Filesystem $files) {}

    public function absolutePath(RelativePath $path): string
    {
        return rtrim($this->root, '/').'/'.$path->value;
    }

    public function prepareHlsDirectory(MediaAssetId $id): string
    {
        $directory = $this->hlsDirectory($id);

        // Une relance repart d'un dossier vide : pas de segments d'un essai précédent.
        $this->files->deleteDirectory($directory);

        if (! $this->files->makeDirectory($directory, 0755, true) && ! $this->files->isDirectory($directory)) {
            throw new RuntimeException('Impossible de créer le dossier de sortie du flux.');
        }

        return $directory;
    }

    public function hlsRelativePath(MediaAssetId $id): string
    {
        return 'hls/'.$id->value;
    }

    public function deleteFile(RelativePath $path): void
    {
        $this->files->delete($this->absolutePath($path));
    }

    public function deleteHlsDirectory(MediaAssetId $id): void
    {
        $this->files->deleteDirectory($this->hlsDirectory($id));
    }

    private function hlsDirectory(MediaAssetId $id): string
    {
        // L'identifiant est un UUID validé : il ne peut pas contenir de séparateur ni de "..".
        return rtrim($this->root, '/').'/'.$this->hlsRelativePath($id);
    }
}
