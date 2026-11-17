<?php

declare(strict_types=1);

namespace HomeFlix\Library\UI\Http\Controllers;

use HomeFlix\Library\Application\Query\ListFavoritesHandler;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class FavoritesController
{
    public function index(Request $request, ListFavoritesHandler $handler): View
    {
        // Le membre est toujours celui de la session.
        return view('library::favorites', ['favorites' => $handler->handle((string) $request->user()?->getAuthIdentifier())]);
    }
}
