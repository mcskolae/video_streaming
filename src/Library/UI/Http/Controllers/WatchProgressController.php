<?php

declare(strict_types=1);

namespace HomeFlix\Library\UI\Http\Controllers;

use HomeFlix\Library\Application\Command\SaveProgress;
use HomeFlix\Library\Application\Command\SaveProgressHandler;
use HomeFlix\Library\Application\Exception\MediaNotPlayable;
use HomeFlix\Library\Application\Query\GetProgressHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Position de lecture du membre connecté. Le membre est toujours celui de la session. */
final class WatchProgressController
{
    public function show(Request $request, string $media, GetProgressHandler $handler): JsonResponse
    {
        return response()->json(['position' => $handler->handle($this->member($request), $media)]);
    }

    public function save(Request $request, string $media, SaveProgressHandler $handler): Response
    {
        $data = $request->validate(['position' => ['required', 'numeric', 'min:0', 'max:86400']]);

        try {
            $handler->handle(new SaveProgress($this->member($request), $media, (float) $data['position']));
        } catch (MediaNotPlayable) {
            abort(404);
        }

        return response()->noContent();
    }

    private function member(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
