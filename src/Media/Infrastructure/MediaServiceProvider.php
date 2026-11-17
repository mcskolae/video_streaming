<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure;

use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use HomeFlix\Media\Infrastructure\Persistence\EloquentMediaAssetRepository;
use Illuminate\Support\ServiceProvider;

final class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MediaAssetRepository::class, EloquentMediaAssetRepository::class);
    }
}
