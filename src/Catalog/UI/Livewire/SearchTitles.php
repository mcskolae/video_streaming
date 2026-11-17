<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI\Livewire;

use HomeFlix\Catalog\Application\Query\PublicCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Recherche en direct : le terme saisi est dans l'URL (`?q=`), donc partageable et conservé au rechargement. */
final class SearchTitles extends Component
{
    #[Url(as: 'q')]
    public string $term = '';

    public function render(PublicCatalog $catalog): View
    {
        $normalized = PublicCatalog::normalizeTerm($this->term);
        $cards = $catalog->search($normalized);

        return view('catalog::livewire.search-titles', [
            'normalized' => $normalized,
            'cards' => $cards,
            'limitReached' => count($cards) >= PublicCatalog::MAX_SEARCH_RESULTS,
        ]);
    }
}
