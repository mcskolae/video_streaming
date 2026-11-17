<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure\Queue;

use HomeFlix\Media\Application\Command\TranscodeMedia;
use HomeFlix\Media\Application\Command\TranscodeMediaHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Tâche de transcodage : une seule tentative (un échec se relance depuis l'administration), jamais deux en même temps. */
final class TranscodeMediaJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 14400;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $mediaAssetId)
    {
        $this->onQueue('transcoding');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        // Un seul transcodage à la fois, même avec plusieurs workers : le CPU n'y suffirait pas.
        return [(new WithoutOverlapping('transcoding'))->releaseAfter(60)->expireAfter($this->timeout + 600)];
    }

    public function handle(TranscodeMediaHandler $handler): void
    {
        $handler->handle(new TranscodeMedia($this->mediaAssetId));
    }

    /** La tâche elle-même a échoué (délai dépassé, mémoire) : le média ne doit pas rester « en cours ». */
    public function failed(Throwable $exception): void
    {
        app(TranscodeMediaHandler::class)->fail($this->mediaAssetId, 'Le transcodage a été interrompu : '.mb_substr($exception->getMessage(), 0, 300));
    }
}
