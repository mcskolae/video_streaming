<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use HomeFlix\Media\Application\Port\MediaStorage;
use HomeFlix\Media\Domain\Model\MediaAssetId;
use HomeFlix\Media\Domain\Model\RelativePath;

final class FakeMediaStorage implements MediaStorage
{
    /** @var list<string> */
    public array $deletedFiles = [];

    /** @var list<string> */
    public array $deletedDirectories = [];

    /** @var list<string> */
    public array $preparedDirectories = [];

    public function absolutePath(RelativePath $path): string
    {
        return '/media/'.$path->value;
    }

    public function prepareHlsDirectory(MediaAssetId $id): string
    {
        $this->preparedDirectories[] = $id->value;

        return '/media/'.$this->hlsRelativePath($id);
    }

    public function hlsRelativePath(MediaAssetId $id): string
    {
        return 'hls/'.$id->value;
    }

    /** @var list<string> fichiers qui n'existent pas (tout le reste existe) */
    public array $missing = [];

    public function exists(RelativePath $path): bool
    {
        return ! in_array($path->value, $this->missing, true);
    }

    public function deleteFile(RelativePath $path): void
    {
        $this->deletedFiles[] = $path->value;
    }

    public function deleteHlsDirectory(MediaAssetId $id): void
    {
        $this->deletedDirectories[] = $id->value;
    }
}
