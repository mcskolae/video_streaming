<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI\Http\Controllers;

use HomeFlix\Catalog\Application\Query\PublicCatalog;
use Illuminate\Http\Response;

/** Fichiers lus par les moteurs de recherche : le plan du site et les consignes aux robots. */
final class SeoController
{
    /** Pages que les robots n'ont pas à explorer : privées, sans contenu propre ou trop lourdes (segments vidéo). */
    private const DISALLOW = ['/admin', '/profile', '/recherche', '/livewire', '/hls/'];

    public function sitemap(PublicCatalog $catalog): Response
    {
        return response()
            ->view('catalog::sitemap', ['titles' => $catalog->sitemap()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            ...array_map(fn (string $path) => 'Disallow: '.$path, self::DISALLOW),
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
