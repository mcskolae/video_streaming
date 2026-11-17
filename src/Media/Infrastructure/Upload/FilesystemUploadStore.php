<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure\Upload;

use HomeFlix\Media\Application\Exception\InvalidUpload;
use HomeFlix\Media\Application\Port\UploadStore;
use HomeFlix\Media\Application\Upload\UploadMeta;
use HomeFlix\Media\Domain\Model\PlayableId;
use HomeFlix\Media\Domain\Model\PlayableKind;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Shared\Domain\Uuid;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;

/**
 * Morceaux d'un envoi sur le disque : `incoming/_uploads/<uuid>/` avec `meta.json` et `chunk_<n>`.
 *
 * L'identifiant d'envoi est validé comme un UUID avant de servir de nom de dossier : il ne peut contenir
 * ni séparateur ni `..`.
 */
final readonly class FilesystemUploadStore implements UploadStore
{
    private const DIRECTORY = 'incoming/_uploads';

    public function __construct(private string $root, private Filesystem $files) {}

    public function begin(UploadMeta $meta): void
    {
        $directory = $this->directory($meta->uploadId);
        $this->files->makeDirectory($directory, 0755, true, true);

        $json = json_encode([
            'kind' => $meta->target->kind->value,
            'target' => $meta->target->id->value,
            'extension' => $meta->extension,
            'size' => $meta->sizeBytes,
            'chunks' => $meta->totalChunks,
            'created_at' => time(),
        ], JSON_THROW_ON_ERROR);

        if (file_put_contents($directory.'/meta.json', $json) === false) {
            throw new RuntimeException('Impossible de démarrer l\'envoi.');
        }
    }

    public function meta(string $uploadId): ?UploadMeta
    {
        $path = $this->directoryOrNull($uploadId);

        if ($path === null || ! is_file($path.'/meta.json')) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path.'/meta.json'), true);

        if (! is_array($data) || ! is_string($data['target'] ?? null) || ! is_string($data['extension'] ?? null)
            || ! is_int($data['size'] ?? null) || ! is_int($data['chunks'] ?? null)) {
            return null;
        }

        $kind = PlayableKind::tryFrom((string) ($data['kind'] ?? ''));

        if ($kind === null) {
            return null;
        }

        try {
            $target = PlayableId::fromString($data['target']);
        } catch (InvalidArgumentException) {
            return null;
        }

        return new UploadMeta(
            $uploadId,
            $kind === PlayableKind::Title ? PlayableRef::title($target) : PlayableRef::episode($target),
            $data['extension'],
            $data['size'],
            $data['chunks'],
        );
    }

    public function storeChunk(string $uploadId, int $index, $stream, int $expectedBytes): int
    {
        $directory = $this->directory($uploadId);
        $temporary = $directory.'/chunk_'.$index.'.tmp';
        $out = fopen($temporary, 'wb');

        if ($out === false) {
            throw new RuntimeException('Impossible d\'écrire le morceau.');
        }

        // On lit au plus un octet de plus que prévu : un morceau trop gros est détecté sans le stocker en entier.
        $copied = stream_copy_to_stream($stream, $out, $expectedBytes + 1);
        fclose($out);

        if ($copied !== $expectedBytes) {
            @unlink($temporary);

            throw InvalidUpload::badChunk();
        }

        // Renommage atomique : un morceau n'est jamais vu à moitié écrit, ni avec une mauvaise taille.
        rename($temporary, $directory.'/chunk_'.$index);

        return $copied;
    }

    public function receivedChunks(string $uploadId): array
    {
        $path = $this->directoryOrNull($uploadId);

        if ($path === null) {
            return [];
        }

        $indexes = [];

        foreach (glob($path.'/chunk_*') ?: [] as $file) {
            if (preg_match('/chunk_(\d+)$/', $file, $matches) === 1) {
                $indexes[] = (int) $matches[1];
            }
        }

        sort($indexes);

        return $indexes;
    }

    public function assemble(string $uploadId, string $destinationAbsolutePath): int
    {
        $meta = $this->meta($uploadId) ?? throw InvalidUpload::unknownUpload();
        $directory = $this->directory($uploadId);

        $this->files->makeDirectory(dirname($destinationAbsolutePath), 0755, true, true);
        $out = fopen($destinationAbsolutePath, 'wb');

        if ($out === false) {
            throw new RuntimeException('Impossible de créer le fichier assemblé.');
        }

        $total = 0;

        try {
            for ($index = 0; $index < $meta->totalChunks; $index++) {
                $chunk = $directory.'/chunk_'.$index;

                if (! is_file($chunk)) {
                    throw InvalidUpload::incomplete();
                }

                $in = fopen($chunk, 'rb');

                if ($in === false) {
                    throw InvalidUpload::incomplete();
                }

                $total += (int) stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        return $total;
    }

    public function discard(string $uploadId): void
    {
        $path = $this->directoryOrNull($uploadId);

        if ($path !== null) {
            $this->files->deleteDirectory($path);
        }
    }

    public function purgeInactiveSince(\DateTimeImmutable $before): int
    {
        $base = rtrim($this->root, '/').'/'.self::DIRECTORY;
        $purged = 0;

        foreach (glob($base.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            // Le dossier est modifié à chaque morceau reçu : sa date est celle de la dernière activité.
            $lastActivity = filemtime($directory);

            if ($lastActivity !== false && $lastActivity < $before->getTimestamp()) {
                $this->files->deleteDirectory($directory);
                $purged++;
            }
        }

        return $purged;
    }

    private function directory(string $uploadId): string
    {
        return $this->directoryOrNull($uploadId) ?? throw new InvalidArgumentException('Identifiant d\'envoi invalide.');
    }

    private function directoryOrNull(string $uploadId): ?string
    {
        // Un identifiant qui n'est pas un UUID ne devient jamais un chemin.
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uploadId) !== 1) {
            return null;
        }

        return rtrim($this->root, '/').'/'.self::DIRECTORY.'/'.$uploadId;
    }
}
