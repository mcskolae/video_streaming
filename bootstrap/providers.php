<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use HomeFlix\Catalog\Infrastructure\CatalogServiceProvider;
use HomeFlix\Catalog\UI\CatalogUiServiceProvider;
use HomeFlix\Identity\Infrastructure\IdentityServiceProvider;
use HomeFlix\Identity\UI\IdentityUiServiceProvider;
use HomeFlix\Media\Infrastructure\MediaServiceProvider;
use HomeFlix\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    CatalogServiceProvider::class,
    CatalogUiServiceProvider::class,
    IdentityServiceProvider::class,
    IdentityUiServiceProvider::class,
    MediaServiceProvider::class,
];
