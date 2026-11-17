<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI\Http\Controllers\Admin;

use HomeFlix\Catalog\Application\Command\ImportMovieFromTmdb;
use HomeFlix\Catalog\Application\Command\ImportMovieFromTmdbHandler;
use HomeFlix\Catalog\Application\Command\ImportSeriesFromTmdb;
use HomeFlix\Catalog\Application\Command\ImportSeriesFromTmdbHandler;
use HomeFlix\Catalog\Application\Command\PublishTitle;
use HomeFlix\Catalog\Application\Command\PublishTitleHandler;
use HomeFlix\Catalog\Application\Command\UnpublishTitle;
use HomeFlix\Catalog\Application\Command\UnpublishTitleHandler;
use HomeFlix\Catalog\Application\Exception\MetadataNotFound;
use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Application\Exception\TitleNotFound;
use HomeFlix\Catalog\Application\Port\TitleListReader;
use HomeFlix\Catalog\Application\Query\ListAdminTitles;
use HomeFlix\Catalog\Application\Query\ListAdminTitlesHandler;
use HomeFlix\Catalog\Application\Query\PreviewTmdbMovie;
use HomeFlix\Catalog\Application\Query\PreviewTmdbMovieHandler;
use HomeFlix\Catalog\Application\Query\PreviewTmdbSeries;
use HomeFlix\Catalog\Application\Query\PreviewTmdbSeriesHandler;
use HomeFlix\Catalog\Application\Query\SearchTmdbMovies;
use HomeFlix\Catalog\Application\Query\SearchTmdbMoviesHandler;
use HomeFlix\Catalog\Domain\Exception\TitleCannotBePublished;
use HomeFlix\Catalog\UI\Http\Requests\ImportMovieRequest;
use HomeFlix\Catalog\UI\Http\Requests\ImportSeriesRequest;
use HomeFlix\Catalog\UI\Http\Requests\SearchMoviesRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Administration du catalogue : recherche et import depuis TMDB, liste, publication.
 * Toutes les routes passent par `auth` puis `admin` (voir routes.php).
 */
final class TitleAdminController
{
    public function index(Request $request, ListAdminTitlesHandler $handler): View
    {
        $page = $handler->handle(new ListAdminTitles(max(1, $request->integer('page', 1))));

        return view('catalog::admin.titles.index', [
            'titles' => new LengthAwarePaginator(
                $page->rows,
                $page->total,
                $page->perPage,
                $page->page,
                ['path' => $request->url()],
            ),
        ]);
    }

    public function search(SearchMoviesRequest $request, SearchTmdbMoviesHandler $handler): View
    {
        $query = $request->searchQuery();
        $type = $request->titleType();
        $hits = [];
        $error = null;

        if ($query !== '') {
            try {
                $hits = $handler->handle(new SearchTmdbMovies($query, $type));
            } catch (MetadataProviderUnavailable $exception) {
                report($exception);
                $error = $this->unavailableMessage();
            }
        }

        return view('catalog::admin.titles.search', ['query' => $query, 'type' => $type, 'hits' => $hits, 'error' => $error]);
    }

    public function preview(int $tmdbId, PreviewTmdbMovieHandler $handler): View|RedirectResponse
    {
        try {
            $preview = $handler->handle(new PreviewTmdbMovie($tmdbId));
        } catch (MetadataNotFound) {
            return redirect()->route('admin.titles.search')->with('error', 'Ce film est introuvable sur TMDB.');
        } catch (MetadataProviderUnavailable $exception) {
            report($exception);

            return redirect()->route('admin.titles.search')->with('error', $this->unavailableMessage());
        }

        return view('catalog::admin.titles.preview', ['preview' => $preview]);
    }

    public function import(ImportMovieRequest $request, ImportMovieFromTmdbHandler $handler): RedirectResponse
    {
        try {
            $result = $handler->handle(new ImportMovieFromTmdb($request->tmdbId()));
        } catch (MetadataNotFound) {
            return back()->with('error', 'Ce film est introuvable sur TMDB.');
        } catch (MetadataProviderUnavailable $exception) {
            report($exception);

            return back()->with('error', $this->unavailableMessage());
        }

        return redirect()->route('admin.titles.index')->with(
            'status',
            $result->created
                ? 'Film importé en brouillon.'
                : 'Ce film était déjà au catalogue : sa fiche a été mise à jour.',
        );
    }

    public function previewSeries(int $tmdbId, PreviewTmdbSeriesHandler $handler): View|RedirectResponse
    {
        try {
            $preview = $handler->handle(new PreviewTmdbSeries($tmdbId));
        } catch (MetadataNotFound) {
            return redirect()->route('admin.titles.search', ['type' => 'series'])->with('error', 'Cette série est introuvable sur TMDB.');
        } catch (MetadataProviderUnavailable $exception) {
            report($exception);

            return redirect()->route('admin.titles.search', ['type' => 'series'])->with('error', $this->unavailableMessage());
        }

        return view('catalog::admin.titles.preview-series', ['preview' => $preview]);
    }

    public function importSeries(ImportSeriesRequest $request, ImportSeriesFromTmdbHandler $handler): RedirectResponse
    {
        try {
            $result = $handler->handle(new ImportSeriesFromTmdb($request->tmdbId()));
        } catch (MetadataNotFound) {
            return back()->with('error', 'Cette série est introuvable sur TMDB.');
        } catch (MetadataProviderUnavailable $exception) {
            report($exception);

            return back()->with('error', $this->unavailableMessage());
        }

        return redirect()->route('admin.titles.show', $result->titleId->value)->with(
            'status',
            $result->created
                ? 'Série importée en brouillon avec ses saisons et ses épisodes.'
                : 'Cette série était déjà au catalogue : sa fiche, ses saisons et ses épisodes ont été mis à jour.',
        );
    }

    /** Les saisons et les épisodes d'une série, avec le lien d'envoi de la vidéo de chaque épisode. */
    public function show(string $title, TitleListReader $reader): View
    {
        $series = $reader->adminSeries($title) ?? abort(404);

        return view('catalog::admin.titles.show', ['series' => $series]);
    }

    public function publish(string $title, PublishTitleHandler $handler): RedirectResponse
    {
        try {
            $handler->handle(new PublishTitle($title));
        } catch (TitleNotFound|InvalidArgumentException) {
            abort(404);
        } catch (TitleCannotBePublished) {
            return back()->with('error', 'Publication impossible : aucun média n\'est prêt pour ce titre.');
        }

        return back()->with('status', 'Titre publié.');
    }

    public function unpublish(string $title, UnpublishTitleHandler $handler): RedirectResponse
    {
        try {
            $handler->handle(new UnpublishTitle($title));
        } catch (TitleNotFound|InvalidArgumentException) {
            abort(404);
        }

        return back()->with('status', 'Titre repassé en brouillon.');
    }

    /** Message affiché à l'administrateur : jamais le détail technique, qui est dans les journaux. */
    private function unavailableMessage(): string
    {
        return 'TMDB est indisponible ou mal configuré. Réessayez plus tard ou vérifiez la clé TMDB.';
    }
}
