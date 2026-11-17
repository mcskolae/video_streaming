<?php

declare(strict_types=1);

namespace HomeFlix\Media\UI\Http\Controllers;

use HomeFlix\Media\Application\Command\CompleteUpload;
use HomeFlix\Media\Application\Command\CompleteUploadHandler;
use HomeFlix\Media\Application\Command\ReceiveChunk;
use HomeFlix\Media\Application\Command\ReceiveChunkHandler;
use HomeFlix\Media\Application\Command\StartUpload;
use HomeFlix\Media\Application\Command\StartUploadHandler;
use HomeFlix\Media\Application\Exception\InvalidUpload;
use HomeFlix\Media\Application\Port\UploadStore;
use HomeFlix\Media\Application\Query\GetMediaOverview;
use HomeFlix\Media\Application\Query\GetMediaOverviewHandler;
use HomeFlix\Media\Application\Query\MediaOverview;
use HomeFlix\Media\Domain\Model\PlayableKind;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Envoi d'une vidéo par morceaux, depuis l'administration. Toutes les routes passent par `auth` puis `admin`.
 *
 * Les réponses sont en JSON pour le script de la page ; une erreur d'envoi donne un message lisible (422),
 * jamais le détail technique.
 */
final class MediaUploadController
{
    public function index(): View
    {
        return view('media::admin.index');
    }

    public function show(string $kind, string $target, GetMediaOverviewHandler $handler): View
    {
        $overview = $this->overview($kind, $target, $handler) ?? abort(404);

        return view('media::admin.show', ['overview' => $overview]);
    }

    public function start(Request $request, StartUploadHandler $handler): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'in:title,episode'],
            'target_id' => ['required', 'string', 'uuid'],
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'total_chunks' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $session = $handler->handle(new StartUpload(
                PlayableKind::from($data['kind']),
                $data['target_id'],
                $data['filename'],
                (int) $data['size'],
                (int) $data['total_chunks'],
            ));
        } catch (InvalidUpload $exception) {
            return $this->refused($exception);
        }

        return response()->json([
            'upload_id' => $session->uploadId,
            'chunk_size' => $session->chunkSizeBytes,
            'total_chunks' => $session->totalChunks,
        ], 201);
    }

    /** Morceaux déjà reçus : permet de reprendre un envoi interrompu. */
    public function status(string $upload, UploadStore $store): JsonResponse
    {
        $meta = $store->meta($upload);

        if ($meta === null) {
            return $this->refused(InvalidUpload::unknownUpload(), 404);
        }

        return response()->json(['received' => $store->receivedChunks($upload), 'total_chunks' => $meta->totalChunks]);
    }

    public function chunk(Request $request, string $upload, int $index, ReceiveChunkHandler $handler): JsonResponse
    {
        try {
            $stream = $request->getContent(true);

            if (! is_resource($stream)) {
                throw InvalidUpload::badChunk();
            }

            $size = $handler->handle(new ReceiveChunk($upload, $index, $stream));
        } catch (InvalidUpload $exception) {
            return $this->refused($exception);
        }

        return response()->json(['received' => $index, 'size' => $size]);
    }

    public function complete(string $upload, CompleteUploadHandler $handler): JsonResponse
    {
        try {
            $assetId = $handler->handle(new CompleteUpload($upload));
        } catch (InvalidUpload $exception) {
            return $this->refused($exception);
        }

        return response()->json(['media_id' => $assetId->value], 201);
    }

    private function overview(string $kind, string $target, GetMediaOverviewHandler $handler): ?MediaOverview
    {
        $kindEnum = PlayableKind::tryFrom($kind);

        if ($kindEnum === null) {
            return null;
        }

        try {
            return $handler->handle(new GetMediaOverview($kindEnum, $target));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function refused(InvalidUpload $exception, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage()], $status);
    }
}
