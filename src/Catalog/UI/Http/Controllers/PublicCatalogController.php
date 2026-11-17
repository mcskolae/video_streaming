<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI\Http\Controllers;

use HomeFlix\Catalog\Application\Query\PublicCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Pages publiques du catalogue : accessibles sans compte, titres publiés uniquement. */
final class PublicCatalogController
{
    public function home(PublicCatalog $catalog): View
    {
        return view('catalog::public.home', ['latest' => $catalog->latest(), 'genres' => $catalog->genres()]);
    }

    public function genre(int $tmdbId, Request $request, PublicCatalog $catalog): View
    {
        $genre = $catalog->genre($tmdbId) ?? abort(404);
        $page = $catalog->byGenre($tmdbId, $request->integer('page', 1));

        return view('catalog::public.genre', [
            'genre' => $genre,
            'titles' => new LengthAwarePaginator($page->cards, $page->total, $page->perPage, $page->page, ['path' => $request->url()]),
        ]);
    }

    public function search(Request $request): View|RedirectResponse
    {
        // `?q[]=x` n'est pas un terme de recherche : on repart d'une page propre plutôt que de faire échouer le composant.
        if ($request->query->has('q') && ! is_string($request->query('q'))) {
            return redirect()->route('search');
        }

        return view('catalog::public.search');
    }

    public function show(string $slug, PublicCatalog $catalog): View
    {
        return view('catalog::public.title', ['title' => $catalog->show($slug) ?? abort(404)]);
    }
}
