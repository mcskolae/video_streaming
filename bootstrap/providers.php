<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use HomeFlix\Catalog\Infrastructure\CatalogServiceProvider;
use HomeFlix\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    CatalogServiceProvider::class,
];
