<?php

declare(strict_types=1);

namespace HomeFlix\Library\UI\View;

use HomeFlix\Library\Application\Query\FavoriteCard;
use HomeFlix\Library\Application\Query\ListFavoritesHandler;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

/** « Ma liste » : les favoris du membre sur l'accueil. Rien pour un visiteur, ni pour un membre sans favori. */
final class MyList extends Component
{
    /** @var list<FavoriteCard> */
    public array $items = [];

    public function __construct(ListFavoritesHandler $handler)
    {
        $member = Auth::id();

        if ($member !== null) {
            $this->items = $handler->handle((string) $member);
        }
    }

    public function shouldRender(): bool
    {
        return $this->items !== [];
    }

    public function render(): View
    {
        return view('library::my-list');
    }
}
