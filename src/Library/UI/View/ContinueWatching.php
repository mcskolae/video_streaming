<?php

declare(strict_types=1);

namespace HomeFlix\Library\UI\View;

use HomeFlix\Library\Application\Query\ContinueWatchingItem;
use HomeFlix\Library\Application\Query\ListContinueWatchingHandler;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

/** « Reprendre la lecture » : rien pour un visiteur, ni pour un membre qui n'a rien en cours. */
final class ContinueWatching extends Component
{
    /** @var list<ContinueWatchingItem> */
    public array $items = [];

    public function __construct(ListContinueWatchingHandler $handler)
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
        return view('library::continue-watching');
    }
}
