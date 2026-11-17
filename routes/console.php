<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Les envois de vidéo abandonnés (plus de 24 h sans activité) sont supprimés chaque nuit.
Schedule::command('media:purge-uploads')->dailyAt('04:00')->onOneServer();
