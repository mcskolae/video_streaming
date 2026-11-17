<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Model;

use DateTimeImmutable;
use HomeFlix\Media\Domain\Event\MediaAssetFailed;
use HomeFlix\Media\Domain\Event\MediaAssetReady;
use HomeFlix\Media\Domain\Exception\InvalidMediaTransition;
use HomeFlix\Media\Domain\Exception\RenditionAlreadyExists;
use HomeFlix\Shared\Domain\AggregateRoot;
use InvalidArgumentException;

/**
 * Vidéo d'un film ou d'un épisode, de l'envoi au flux HLS prêt à lire.
 *
 * Transitions autorisées, et seulement celles-ci :
 *
 *   uploaded   -> queued       (queue)
 *   queued     -> processing   (startProcessing)
 *   processing -> ready        (markReady)
 *   uploaded | queued | processing -> failed   (markFailed)
 *   failed     -> queued       (retry)
 *
 * `ready` est final : un média prêt ne repasse jamais en traitement.
 */
final class MediaAsset extends AggregateRoot
{
    /** @var array<string, Rendition> qualités indexées par libellé */
    private array $renditions = [];

    private function __construct(
        private readonly MediaAssetId $id,
        private readonly PlayableRef $target,
        private readonly RelativePath $sourcePath,
        private MediaStatus $status,
        private ?Duration $duration,
        private ?string $errorMessage,
        private ?RelativePath $hlsMasterPath,
    ) {}

    /** Un fichier vient d'être reçu et validé. */
    public static function upload(MediaAssetId $id, PlayableRef $target, RelativePath $sourcePath): self
    {
        return new self($id, $target, $sourcePath, MediaStatus::Uploaded, null, null, null);
    }

    /**
     * Recharge un média déjà persisté. N'enregistre aucun événement.
     *
     * @param  list<Rendition>  $renditions
     */
    public static function reconstitute(
        MediaAssetId $id,
        PlayableRef $target,
        RelativePath $sourcePath,
        MediaStatus $status,
        ?Duration $duration,
        ?string $errorMessage,
        ?RelativePath $hlsMasterPath,
        array $renditions,
    ): self {
        $asset = new self($id, $target, $sourcePath, $status, $duration, $errorMessage, $hlsMasterPath);

        foreach ($renditions as $rendition) {
            $asset->renditions[$rendition->label] = $rendition;
        }

        return $asset;
    }

    public function id(): MediaAssetId
    {
        return $this->id;
    }

    public function target(): PlayableRef
    {
        return $this->target;
    }

    public function sourcePath(): RelativePath
    {
        return $this->sourcePath;
    }

    public function status(): MediaStatus
    {
        return $this->status;
    }

    public function duration(): ?Duration
    {
        return $this->duration;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function hlsMasterPath(): ?RelativePath
    {
        return $this->hlsMasterPath;
    }

    /** @return list<Rendition> */
    public function renditions(): array
    {
        return array_values($this->renditions);
    }

    public function isReady(): bool
    {
        return $this->status === MediaStatus::Ready;
    }

    /** Met le média en file d'attente de transcodage. */
    public function queue(): void
    {
        $this->assertStatus([MediaStatus::Uploaded], 'queue');
        $this->status = MediaStatus::Queued;
    }

    /** Le transcodage démarre. */
    public function startProcessing(): void
    {
        $this->assertStatus([MediaStatus::Queued], 'start processing');
        $this->status = MediaStatus::Processing;
    }

    /**
     * Le transcodage a réussi. Il faut au moins une qualité.
     *
     * @param  list<Rendition>  $renditions
     *
     * @throws InvalidArgumentException sans qualité
     * @throws RenditionAlreadyExists si deux qualités portent le même libellé
     */
    public function markReady(Duration $duration, RelativePath $hlsMasterPath, array $renditions, DateTimeImmutable $at): void
    {
        $this->assertStatus([MediaStatus::Processing], 'mark ready');

        if ($renditions === []) {
            throw new InvalidArgumentException('A ready media needs at least one rendition.');
        }

        $byLabel = [];

        foreach ($renditions as $rendition) {
            if (isset($byLabel[$rendition->label])) {
                throw RenditionAlreadyExists::withLabel($rendition->label);
            }

            $byLabel[$rendition->label] = $rendition;
        }

        $this->status = MediaStatus::Ready;
        $this->duration = $duration;
        $this->hlsMasterPath = $hlsMasterPath;
        $this->renditions = $byLabel;
        $this->errorMessage = null;
        $this->record(new MediaAssetReady($this->id, $this->target, $at));
    }

    /** Le traitement a échoué. Le message est conservé pour l'administrateur. */
    public function markFailed(string $message, DateTimeImmutable $at): void
    {
        $this->assertStatus([MediaStatus::Uploaded, MediaStatus::Queued, MediaStatus::Processing], 'mark failed');

        $message = trim($message);

        if ($message === '') {
            throw new InvalidArgumentException('A failure needs a message.');
        }

        $this->status = MediaStatus::Failed;
        $this->errorMessage = mb_substr($message, 0, 2000);
        $this->record(new MediaAssetFailed($this->id, $this->target, $at));
    }

    /** Relance un média en échec : il repart en file, sans message d'erreur. */
    public function retry(): void
    {
        $this->assertStatus([MediaStatus::Failed], 'retry');
        $this->status = MediaStatus::Queued;
        $this->errorMessage = null;
    }

    /** @param  list<MediaStatus>  $allowed */
    private function assertStatus(array $allowed, string $action): void
    {
        if (! in_array($this->status, $allowed, true)) {
            throw InvalidMediaTransition::from($this->status, $action);
        }
    }
}
