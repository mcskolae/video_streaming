<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI\Http\Controllers;

use HomeFlix\Catalog\Application\Query\PublicCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Pages publiques du catalogue : accessibles sans compte, titres publiés uniquement. */
final class PublicCatalogController
{
    /** Nombre de titres affichés dans la rangée d'un genre, sur l'accueil. */
    private const GENRE_ROW_LENGTH = 12;

    public function home(PublicCatalog $catalog): View
    {
        $genreRows = [];

        foreach ($catalog->genres() as $genre) {
            $genreRows[] = [
                'genre' => $genre,
                'cards' => array_slice($catalog->byGenre($genre->tmdbId, 1)->cards, 0, self::GENRE_ROW_LENGTH),
            ];
        }

        return view('catalog::public.home', ['latest' => $catalog->latest(), 'genreRows' => $genreRows]);
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
