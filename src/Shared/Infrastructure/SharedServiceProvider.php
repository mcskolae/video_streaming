<?php

declare(strict_types=1);

namespace HomeFlix\Shared\Infrastructure;

use HomeFlix\Shared\Application\EventDispatcher;
use HomeFlix\Shared\Application\IdGenerator;
use HomeFlix\Shared\Domain\Clock;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(IdGenerator::class, RamseyIdGenerator::class);
        $this->app->singleton(EventDispatcher::class, LaravelEventDispatcher::class);
    }
}
